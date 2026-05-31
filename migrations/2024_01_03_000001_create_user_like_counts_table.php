<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Tabela companheira com o total de likes recebidos por usuário (likes em posts
 * de sua autoria). Denormalizado para o bloco de stats do post evitar um
 * COUNT por linha sobre `post_likes` (N+1, §38.1). Mantido fresco pelo
 * PostLikeListener; backfill inicial agrega `post_likes` quando o flarum/likes
 * está presente.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('tfb_user_like_counts')) {
            $schema->create('tfb_user_like_counts', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->primary();
                $table->unsignedInteger('likes_received')->default(0);

                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->cascadeOnDelete();
            });
        }

        $db = $schema->getConnection();
        if ($schema->hasTable('post_likes') && $schema->hasTable('posts')) {
            $db->statement(
                'INSERT INTO tfb_user_like_counts (user_id, likes_received)
                 SELECT p.user_id, COUNT(*)
                 FROM post_likes pl
                 JOIN posts p ON p.id = pl.post_id
                 WHERE p.user_id IS NOT NULL
                 GROUP BY p.user_id
                 ON DUPLICATE KEY UPDATE likes_received = VALUES(likes_received)'
            );
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('tfb_user_like_counts');
    },
];
