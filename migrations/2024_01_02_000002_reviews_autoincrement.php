<?php

use Illuminate\Database\Schema\Builder;

/**
 * Torna os `id` das tabelas de reviews AUTO_INCREMENT. Na criação as tabelas
 * usam `id` não-incremental para preservar os ids do MyBB no import; depois do
 * import, novos cadastros (produtos/reviews/comentários criados no Flarum)
 * precisam de id automático. O AUTO_INCREMENT continua a partir do maior id já
 * presente, sem colidir com os importados. MySQL-only (driver do site).
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        $tables = [
            'tfb_review_categories',
            'tfb_review_fields',
            'tfb_products',
            'tfb_product_reviews',
            'tfb_review_field_ratings',
            'tfb_review_photos',
            'tfb_review_comments',
        ];

        // FKs referenciam estes `id`; o MySQL recusa MODIFY (erro 1833) com as
        // checagens ligadas. Desligamos temporariamente — o MODIFY não altera
        // valores, então a integridade referencial permanece.
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $t) {
                if ($schema->hasTable($t)) {
                    $db->statement("ALTER TABLE `{$t}` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    },
    'down' => function (Builder $schema) {
        // Sem rollback: remover AUTO_INCREMENT de colunas já em uso não é seguro.
    },
];
