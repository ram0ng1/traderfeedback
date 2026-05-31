<?php

use Illuminate\Database\Schema\Builder;

/**
 * Concede as permissões padrão do Trade Feedback, espelhando a configuração do
 * MyBB com o mapeamento de grupos MyBB→Flarum: Super Moderators(3)→Moderador(4);
 * Administrators já são cobertos pelo Admin do Flarum (grupo 1, que ignora
 * checagens). Membros(3) podem dar e reportar feedback; Moderadores(4) podem
 * excluir e moderar. Idempotente.
 *
 * O Migrator do Flarum 2 sempre injeta o SchemaBuilder no closure — a conexão
 * é obtida via `$schema->getConnection()`.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $rows = [
            ['group_id' => 3, 'permission' => 'huseyinfiliz-traderfeedback.give'],
            ['group_id' => 3, 'permission' => 'huseyinfiliz-traderfeedback.report'],
            ['group_id' => 4, 'permission' => 'huseyinfiliz-traderfeedback.delete'],
            ['group_id' => 4, 'permission' => 'huseyinfiliz-traderfeedback.moderate'],
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
                'huseyinfiliz-traderfeedback.give',
                'huseyinfiliz-traderfeedback.report',
                'huseyinfiliz-traderfeedback.delete',
                'huseyinfiliz-traderfeedback.moderate',
            ])
            ->delete();
    },
];
