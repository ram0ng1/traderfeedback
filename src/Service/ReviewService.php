<?php

namespace HuseyinFiliz\TraderFeedback\Service;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Foundation\ValidationException;
use HuseyinFiliz\TraderFeedback\Models\Product;
use HuseyinFiliz\TraderFeedback\Models\ProductReview;
use HuseyinFiliz\TraderFeedback\Models\ReviewCategory;
use HuseyinFiliz\TraderFeedback\Models\ReviewComment;
use HuseyinFiliz\TraderFeedback\Models\ReviewField;
use HuseyinFiliz\TraderFeedback\Models\ReviewFieldRating;
use HuseyinFiliz\TraderFeedback\Models\ReviewPhoto;

/**
 * Regra de negócio de escrita do Community Reviews: criação de produtos,
 * reviews (com notas por campo + fotos) e comentários. Cada fluxo multi-write
 * roda em transação e recalcula os ratings agregados (§62).
 */
class ReviewService
{
    public const PERM_CREATE = 'huseyinfiliz-traderfeedback.reviews.create';
    public const PERM_MODERATE = 'huseyinfiliz-traderfeedback.reviews.moderate';

    public function createProduct(Context $context): Product
    {
        $actor = $context->getActor();
        $actor->assertRegistered();
        $actor->assertCan(self::PERM_CREATE);

        $attrs = (array) ($context->body()['data']['attributes'] ?? []);
        $name = trim((string) ($attrs['name'] ?? ''));
        $categoryId = (int) ($attrs['categoryId'] ?? 0);

        if ($name === '' || mb_strlen($name) > 255) {
            throw new ValidationException(['name' => 'Invalid product name.']);
        }
        if (! ReviewCategory::query()->whereKey($categoryId)->exists()) {
            throw new ValidationException(['categoryId' => 'Invalid category.']);
        }

        $now = Carbon::now();
        $product = new Product();
        $product->category_id = $categoryId;
        $product->name = $name;
        $product->user_id = (int) $actor->id;
        $product->views = 0;
        $product->cached_rating = 0;
        $product->review_count = 0;
        $product->created_at = $now;
        $product->updated_at = $now;
        $product->save();

        return $product;
    }

    public function createReview(Context $context): ProductReview
    {
        $actor = $context->getActor();
        $actor->assertRegistered();
        $actor->assertCan(self::PERM_CREATE);

        $attrs = (array) ($context->body()['data']['attributes'] ?? []);
        $productId = (int) ($attrs['productId'] ?? 0);

        $product = Product::query()->find($productId);
        if (! $product) {
            throw new ValidationException(['productId' => 'Invalid product.']);
        }

        $comment = trim((string) ($attrs['comment'] ?? ''));
        if ($comment === '') {
            throw new ValidationException(['comment' => 'Comment is required.']);
        }

        $fields = $this->normalizeFields($attrs['fields'] ?? [], $product->category_id);
        $photos = $this->normalizePhotos($attrs['photos'] ?? []);

        $rating = $fields === []
            ? max(0.0, min(5.0, (float) ($attrs['rating'] ?? 0)))
            : round(array_sum(array_column($fields, 'rating')) / count($fields), 2);

        $now = Carbon::now();

        $review = ProductReview::query()->getConnection()->transaction(function () use ($product, $actor, $comment, $attrs, $fields, $photos, $rating, $now) {
            $review = new ProductReview();
            $review->product_id = (int) $product->id;
            $review->user_id = (int) $actor->id;
            $review->merchant_user_id = null;
            $review->price = isset($attrs['price']) ? mb_substr(trim((string) $attrs['price']), 0, 30) : null;
            $review->url = $this->safeUrl((string) ($attrs['url'] ?? ''));
            $review->comment = $comment;
            $review->rating = $rating;
            $review->created_at = $now;
            $review->updated_at = $now;
            $review->save();

            foreach ($fields as $f) {
                $fr = new ReviewFieldRating();
                $fr->review_id = (int) $review->id;
                $fr->field_id = $f['fieldId'];
                $fr->rating = $f['rating'];
                $fr->comment = $f['comment'];
                $fr->save();
            }

            foreach ($photos as $i => $url) {
                $ph = new ReviewPhoto();
                $ph->review_id = (int) $review->id;
                $ph->url = $url;
                $ph->thumbnail_url = $url;
                $ph->position = $i + 1;
                $ph->save();
            }

            $this->recomputeProduct($product);

            return $review;
        });

        $review->load(['user', 'photos', 'fieldRatings', 'product']);

        return $review;
    }

    public function createComment(Context $context): ReviewComment
    {
        $actor = $context->getActor();
        $actor->assertRegistered();
        $actor->assertCan(self::PERM_CREATE);

        $attrs = (array) ($context->body()['data']['attributes'] ?? []);
        $productId = (int) ($attrs['productId'] ?? 0);
        $reviewId = (int) ($attrs['reviewId'] ?? 0) ?: null;
        $comment = trim((string) ($attrs['comment'] ?? ''));

        if (! Product::query()->whereKey($productId)->exists()) {
            throw new ValidationException(['productId' => 'Invalid product.']);
        }
        if ($comment === '') {
            throw new ValidationException(['comment' => 'Comment is required.']);
        }
        if ($reviewId !== null && ! ProductReview::query()->whereKey($reviewId)->exists()) {
            $reviewId = null;
        }

        $now = Carbon::now();
        $c = new ReviewComment();
        $c->product_id = $productId;
        $c->review_id = $reviewId;
        $c->user_id = (int) $actor->id;
        $c->comment = $comment;
        $c->created_at = $now;
        $c->updated_at = $now;
        $c->save();

        $c->load('user');

        return $c;
    }

    public function deleteReview(Context $context): void
    {
        $actor = $context->getActor();
        $review = ProductReview::query()->findOrFail($context->modelId);

        $isOwner = (int) $actor->id === (int) $review->user_id;
        if (! $isOwner && ! $actor->hasPermission(self::PERM_MODERATE)) {
            $actor->assertCan(self::PERM_MODERATE);
        }

        $product = $review->product;
        $review->delete();
        if ($product) {
            $this->recomputeProduct($product);
        }
    }

    /**
     * Normaliza as notas por campo: aceita só campos válidos da categoria do
     * produto, com nota 1–5.
     *
     * @param mixed $raw
     * @return array<int, array{fieldId:int, rating:int, comment:?string}>
     */
    private function normalizeFields($raw, int $categoryId): array
    {
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $valid = ReviewField::query()->where('category_id', $categoryId)->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fid = (int) ($row['fieldId'] ?? 0);
            $rating = (int) ($row['rating'] ?? 0);
            if (! isset($valid[$fid]) || $rating < 1 || $rating > 5) {
                continue;
            }
            $out[] = [
                'fieldId' => $fid,
                'rating' => $rating,
                'comment' => isset($row['comment']) && trim((string) $row['comment']) !== ''
                    ? mb_substr(trim((string) $row['comment']), 0, 2000)
                    : null,
            ];
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizePhotos($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $u) {
            $url = $this->safeUrl((string) $u);
            if ($url !== null) {
                $out[] = $url;
            }
            if (count($out) >= 10) {
                break;
            }
        }
        return $out;
    }

    private function safeUrl(string $raw): ?string
    {
        $raw = trim($raw);
        return ($raw !== '' && preg_match('#^https?://#i', $raw) && mb_strlen($raw) <= 1000) ? $raw : null;
    }

    private function recomputeProduct(Product $product): void
    {
        $agg = ProductReview::query()
            ->where('product_id', $product->id)
            ->selectRaw('COUNT(*) AS c, AVG(rating) AS avg_rating')
            ->first();

        $product->review_count = (int) ($agg->c ?? 0);
        $product->cached_rating = round((float) ($agg->avg_rating ?? 0), 2);
        $product->save();
    }
}
