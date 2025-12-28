@extends('admin.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">All Users</h4>
        </div>

        <div class="card-body table-responsive">

            {{-- 🔍 Search Form --}}
            <form method="GET" action="{{ route('users.index') }}" class="mb-3 d-flex gap-2">
                <input type="text" name="search" class="form-control" placeholder="Search by email or wallet address" value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                @if(request('search'))
                    <a href="{{ route('users.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                @endif
            </form>

            {{-- ✅ Users Table --}}
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Wallet Address</th>
                    <th>Wallet Key</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $index => $user)
                    <tr>
                        <td>{{ $index + $users->firstItem() }}</td>
                        <td>{{ $user->created_at->format('Y-m-d') }}</td>
                        <td class="user-name">{{ $user->name }}</td>
                        <td class="user-email">{{ $user->email }}</td>
                        <td class="user-wallet-address">{{ $user->wallet_address }}</td>
                        <td class="user-wallet-key" data-user-id="{{ $user->id }}">
                            <span class="masked-key">••••••••••</span>
                            <span class="real-key d-none"></span>

                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary ms-2 toggleWalletBtn"
                                    data-id="{{ $user->id }}"
                                    title="Reveal wallet key">
                                <i class="fas fa-eye"></i>
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary ms-1 copyWalletBtn d-none"
                                    data-id="{{ $user->id }}"
                                    title="Copy wallet key">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                        <td>
                            <span class="badge {{ $user->is_block ? 'bg-danger' : 'bg-success' }}">
                                {{ $user->is_block ? 'Blocked' : 'Active' }}
                            </span>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-primary editUserBtn"
                                    data-id="{{ $user->id }}"
                                    data-name="{{ $user->name }}"
                                    data-email="{{ $user->email }}"
                                    data-block="{{ $user->is_block }}">
                                <i class="fas fa-edit"></i> Manage
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $users->appends(request()->query())->links('admin.layouts.partials.__pagination') }}
            </div>
        </div>
    </div>

    <!-- ✅ Edit Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editUserForm">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="editUserName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="editUserEmail" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="editUserStatus" class="form-select">
                                <option value="0">Active</option>
                                <option value="1">Blocked</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ✅ Script --}}
    <script>
        $(document).ready(function () {
            let editModal;

            // 🟢 Open Edit Modal
            $(document).on('click', '.editUserBtn', function () {
                $('#editUserId').val($(this).data('id'));
                $('#editUserName').val($(this).data('name'));
                $('#editUserEmail').val($(this).data('email'));
                $('#editUserStatus').val($(this).data('block'));
                editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                editModal.show();
            });

            // 🟢 Submit Edit Form
            $('#editUserForm').on('submit', function (e) {
                e.preventDefault();
                const userId = $('#editUserId').val();
                const formData = {
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT',
                    name: $('#editUserName').val(),
                    email: $('#editUserEmail').val(),
                    is_block: $('#editUserStatus').val(),
                };

                $.ajax({
                    url: `/admin/users/${userId}`,
                    type: 'POST',
                    data: formData,
                    beforeSend: function () {
                        $('#editUserForm button[type=submit]')
                            .html('<i class="fas fa-spinner fa-spin"></i> Saving...')
                            .prop('disabled', true);
                    },
                    success: function (res) {
                        $('#editUserForm button[type=submit]').html('Save Changes').prop('disabled', false);
                        editModal.hide();

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'User details updated successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        const row = $(`button[data-id='${userId}']`).closest('tr');
                        row.find('.user-name').text(formData.name);
                        row.find('.user-email').text(formData.email);

                        const badge = row.find('span.badge');
                        if (formData.is_block == 1) {
                            badge.removeClass('bg-success').addClass('bg-danger').text('Blocked');
                        } else {
                            badge.removeClass('bg-danger').addClass('bg-success').text('Active');
                        }

                        row.find('.editUserBtn')
                            .data('name', formData.name)
                            .data('email', formData.email)
                            .data('block', formData.is_block);
                    },
                    error: function () {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong.' });
                        $('#editUserForm button[type=submit]').html('Save Changes').prop('disabled', false);
                    }
                });
            });

            // ==============================
            // 🔐 Reveal Wallet Key Section
            // ==============================
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

            // Eye button click
            $(document).on('click', '.toggleWalletBtn', function () {
                const userId = $(this).data('id');
                const cell = $(this).closest('td.user-wallet-key');
                const masked = cell.find('.masked-key');
                const realSpan = cell.find('.real-key');
                const copyBtn = cell.find('.copyWalletBtn');
                const eyeIcon = $(this).find('i');

                if (!realSpan.hasClass('d-none')) {
                    realSpan.addClass('d-none').text('');
                    masked.removeClass('d-none');
                    copyBtn.addClass('d-none');
                    eyeIcon.removeClass('fa-eye-slash').addClass('fa-eye');
                    return;
                }

                Swal.fire({
                    title: 'Enter Reveal Code',
                    input: 'password',
                    inputPlaceholder: 'Enter secure code',
                    showCancelButton: true,
                    confirmButtonText: 'Reveal',
                    showLoaderOnConfirm: true,
                    preConfirm: (code) => {
                        if (!code) {
                            Swal.showValidationMessage('Code is required');
                            return false;
                        }

                        return $.ajax({
                            url: `/admin/users/${userId}/reveal-wallet-key`,
                            type: 'POST',
                            data: { reveal_code: code },
                        }).then(function (res) {
                            if (res.success) return res.wallet_key;
                            throw new Error(res.message || 'Invalid code');
                        }).catch(function (err) {
                            Swal.showValidationMessage(`Failed: ${err.message}`);
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        masked.addClass('d-none');
                        realSpan.removeClass('d-none').text(result.value);
                        copyBtn.removeClass('d-none');
                        eyeIcon.removeClass('fa-eye').addClass('fa-eye-slash');
                        Swal.fire({ icon: 'success', title: 'Revealed!', timer: 1200, showConfirmButton: false });
                    }
                });
            });

            // Copy wallet key
            $(document).on('click', '.copyWalletBtn', function () {
                const text = $(this).closest('td').find('.real-key').text();
                navigator.clipboard.writeText(text);
                Swal.fire({ icon: 'success', title: 'Copied!', timer: 900, showConfirmButton: false });
            });
        });
    </script>
@endsection
