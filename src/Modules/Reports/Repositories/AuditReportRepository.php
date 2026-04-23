<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;

class AuditReportRepository extends BaseRepository
{
    protected string $table = 'audit_logs';
    protected string $alias = 'a';

    protected array $searchable = [
        'a.action',
        'a.entity_type'
    ];

    protected array $filterable = [
        'entity_type',
        'entity_id',
        'user_id'
    ];

    protected array $sortable = [
        'created_at'
    ];
}