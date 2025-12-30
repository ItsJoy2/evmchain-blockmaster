<!-- Sidebar -->

<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="/admin/dashboard" class="logo">
                @if($generalSettings && $generalSettings->logo)
                    <img src="{{ asset('storage/' . $generalSettings->logo) }}" alt="{{ $generalSettings->app_name ?? '3twentyPay' }}" class="navbar-brand" height="50">
                @endif
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="gg-menu-left"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
            </button>
        </div>
        <!-- End Logo Header -->
    </div>

    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">

                <!-- Dashboard -->
                <li class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}">
                    <a href="/admin/dashboard">
                        <i class="fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>


                <!-- Users -->
                <li class="nav-item {{ request()->is('admin/users') ? 'active' : '' }}">
                    <a href="/admin/users">
                        <i class="fas fa-users"></i>
                        <p>All Users</p>
                    </a>
                </li>




                <!-- Plans -->
                <li class="nav-item {{ Str::contains(request()->path(), 'packages') ? 'active' : '' }}">
                    <a href="/admin/packages">
                        <i class="fas fa-database"></i>
                        <p>All Plans</p>
                    </a>
                </li>

                <!-- deposit -->
                <li class="nav-item {{ Str::contains(request()->path(), 'deposit') ? 'active' : '' }}">
                    <a href="/deposit" class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-money-check-alt"></i>
                            <p class="m-0">Deposit</p>
                        </div>
                    </a>
                </li>


                <!-- Transactions -->
                <li class="nav-item {{ request()->is('admin/transactions') ? 'active' : '' }}">
                    <a href="/admin/transactions">
                        <i class="fas fa-money-check"></i>
                        <p>Transactions</p>
                    </a>
                </li>


                <li class="nav-item {{ request()->is('admin/payment-jobs') ? 'active' : '' }}">
                    <a href="/admin/payment-jobs">
                        <i class="fas fa-file-invoice"></i>
                        <p>Invoice</p>
                    </a>
                </li>




                <!-- Cron -->
                <li class="nav-item {{ request()->is('cron') ? 'active' : '' }}">
                    <a href="/cron">
                        <i class="fas fa-school"></i>
                        <p>Cron Job</p>
                    </a>
                </li>


                <!-- Settings -->
                <li class="nav-item nav-item {{ Str::contains(request()->path(), 'holidays') ? 'active' : '' }}">
                    <a data-bs-toggle="collapse" href="#settings">
                        <i class="fas fa-cog"></i>
                        <p>Settings</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse" id="settings">
                        <ul class="nav nav-collapse">
                            <li><a href="token"><span class="sub-item {{ Str::contains(request()->path(), 'token') ? 'active' : '' }}">Token Setting</span></a></li>
                            <li><a href="chain"><span class="sub-item">Chain List</span></a></li>
                            <li><a href="/withdraws/settings"><span class="sub-item">Withdraws Settings</span></a></li>
                            <li><a href="/ReferralsSettings"><span class="sub-item">Referral Settings</span></a></li>
                            <li><a href="/admin/general-settings"><span class="sub-item">App Settings</span></a></li>
                        </ul>
                    </div>
                </li>

            </ul>
        </div>
    </div>
</div>
<!-- End Sidebar -->
