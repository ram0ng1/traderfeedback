<?php

use Illuminate\Database\Schema\Builder;

/**
 * Permissões padrão do Community Reviews, com o mapeamento de grupos
 * MyBB→Flarum: criar reviews/produtos/comentários → Membros(3); moderar
 * (editar/excluir qualquer) → Moderadores(4); Admin(1) já ignora checagens.
 * Idempotente.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $rows = [
            ['group_id' => 3, 'permission' => 'huseyinfiliz-traderfeedback.reviews.create'],
            ['group_id' => 4, 'permission' => 'huseyinfiliz-traderfeedback.reviews.moderate'],
        ];

        foreach ($rows as $row) {
            $exists = $db->table('group_permission')
                ->where('group_id', $row['group_id'])
                ->where('permission', $row['permission'])
                ->exists();

            if (! $exists) {
                $db->table('group_permission')->insert($row + ['created_at' => date('Y-m-d H:i:s')]);
            }
        }
    },
    'down' => function (Builder $schema) {
        $schema->getConnection()->table('group_permission')
            ->whereIn('permission', [
                'huseyinfiliz-traderfeedback.reviews.create',
                'huseyinfiliz-traderfeedback.reviews.moderate',
            ])
            ->delete();
    },
];
