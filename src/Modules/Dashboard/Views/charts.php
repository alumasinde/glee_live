<?php
use App\Modules\Dashboard\Services\DashboardService;
$dashboardService = new DashboardService(); 
?>

<div class="chart-filters">
    <select id="chartRange">
        <option value="30">Last 30 Days</option>
        <option value="90">Last 90 Days</option>
    </select>
</div>

<div class="charts-grid">

    <div class="chart-card">
        <h3>Workflow Status</h3>
        <div class="chart-container">
            <canvas id="workflowChart"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3>Gatepasses</h3>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3>Visits</h3>
        <div class="chart-container">
            <canvas id="weeklyChart"></canvas>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/dashboard-charts.js"></script>

<script>
</script>