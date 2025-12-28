@extends('admin.layouts.app')

@section('content')
    <div class="container mt-4">
        {{-- Dashboard Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Admin Dashboard</h4>
            <span class="text-muted">Updated: {{ now()->format('d M Y, h:i A') }}</span>
        </div>

        {{-- Overview Cards --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-4">📊 Overview</h5>
                <div class="row g-4">
                    @php
                        $stats = [
                            ['label' => 'Total USDT', 'value' => $dashboardData['totalUsddtTrx'], 'icon' => 'fa-coins', 'color' => 'success'],
                            ['label' => 'Today Transaction', 'value' => $dashboardData['today_trx'], 'icon' => 'fa-chart-line', 'color' => 'info'],
                            ['label' => 'Total Transaction', 'value' => $dashboardData['totalTrx'], 'icon' => 'fa-exchange-alt', 'color' => 'primary'],
                            ['label' => 'Total Customers', 'value' => $dashboardData['totalCustomer'], 'icon' => 'fa-users', 'color' => 'warning'],
                            ['label' => 'Total Coin', 'value' => $dashboardData['totalCoin'], 'icon' => 'fa-circle', 'color' => 'secondary'],
                            ['label' => 'Total Network', 'value' => $dashboardData['totalChain'], 'icon' => 'fa-link', 'color' => 'dark'],
                            ['label' => 'Total License', 'value' => $dashboardData['totalLicense'], 'icon' => 'fa-id-badge', 'color' => 'primary'],
                            ['label' => 'Expired License', 'value' => $dashboardData['expireLicense'], 'icon' => 'fa-ban', 'color' => 'danger'],
                        ];
                    @endphp

                    @foreach ($stats as $item)
                        <div class="col-md-3 col-sm-6">
                            <div class="card border-0 shadow-sm h-100 hover-shadow">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-{{ $item['color'] }} bg-opacity-25 text-{{ $item['color'] }} d-flex justify-content-center align-items-center me-3"
                                             style="width: 50px; height: 50px;">
                                            <i class="fas {{ $item['icon'] }} fs-5"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold mb-0">{{ $item['value'] }}</h5>
                                            <small class="text-muted">{{ $item['label'] }}</small>
                                        </div>
                                    </div>
                                    <i class="fas fa-arrow-right text-muted"></i>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Chart Section --}}
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">Transaction Overview</h5>
                        <canvas id="transactionChart" height="220"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">License Status</h5>
                        <canvas id="licenseChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // ✅ Transaction Pie Chart
        const transactionCtx = document.getElementById('transactionChart').getContext('2d');
        new Chart(transactionCtx, {
            type: 'pie',
            data: {
                labels: ['Today', 'Total'],
                datasets: [{
                    data: [{{ $dashboardData['today_trx'] }}, {{ $dashboardData['totalTrx'] }}],
                    backgroundColor: ['#0d6efd', '#198754'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // ✅ License Pie Chart
        const licenseCtx = document.getElementById('licenseChart').getContext('2d');
        new Chart(licenseCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Expired'],
                datasets: [{
                    data: [{{ $dashboardData['totalLicense'] - $dashboardData['expireLicense'] }}, {{ $dashboardData['expireLicense'] }}],
                    backgroundColor: ['#20c997', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>

@endsection
