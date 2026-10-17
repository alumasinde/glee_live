<?php
$showWorkflowChart = can('approval.view');
$showGatepassChart = can('gatepass.view');
$showVisitsChart   = can('visits.view');
$hasCharts         = $showWorkflowChart || $showGatepassChart || $showVisitsChart;
?>

<?php if ($hasCharts): ?>

<div class="chart-filters">
    <select id="chartRange">
        <option value="30">Last 30 Days</option>
        <option value="90">Last 90 Days</option>
    </select>
</div>

<div class="charts-grid">

    <?php if ($showWorkflowChart): ?>
        <div class="chart-card">
            <h3>Workflow Status</h3>
            <div class="chart-container">
                <canvas id="workflowChart"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($showGatepassChart): ?>
        <div class="chart-card">
            <h3>Gatepasses</h3>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($showVisitsChart): ?>
        <div class="chart-card">
            <h3>Visits</h3>
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/dashboard-charts.js"></script>

<?php endif; ?>
