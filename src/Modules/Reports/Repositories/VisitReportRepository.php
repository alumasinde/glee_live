<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;

class VisitReportRepository extends BaseRepository
{
    protected string $table = 'visits';
    protected string $alias = 'v';

    protected array $searchable = [
        'v.purpose'
    ];

    protected array $filterable = [
        'visit_status_id',
        'department_id'
    ];

    protected array $sortable = [
        'checkin_time',
        'created_at'
    ];
}