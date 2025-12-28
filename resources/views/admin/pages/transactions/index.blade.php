@extends('admin.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">All Transactions</h4>
        </div>

        {{-- ✅ Filter Form --}}
        <div class="card-body border-bottom mb-3">
            <form method="GET" action="{{ route('admin.transactions.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User Name</label>
                    <input type="text" name="username" value="{{ request('username') }}" class="form-control" placeholder="Enter user name">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Token Name</label>
                    <input type="text" name="token" value="{{ request('token') }}" class="form-control" placeholder="Token name">
                </div>

                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control">
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- ✅ Transactions Table --}}
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Token</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($transactions as $index => $trx)
                    <tr>
                        <td>{{ $index + $transactions->firstItem() }}</td>
                        <td>{{ $trx->created_at->format('Y-m-d') }}</td>
                        <td>{{ $trx->user->name ?? 'N/A' }}</td>
                        <td class="trx-amount">{{ number_format($trx->amount, 3) }}</td>
                        <td class="trx-token">{{ $trx->token_name }}</td>
                        <td class="trx-type">{{ ucfirst($trx->type) }}</td>
                        <td>
                            <span class="badge {{ $trx->status == 1 ? 'bg-success' : ($trx->status == 'pending' ? 'bg-warning' : 'bg-danger') }}">
                                {{ ucfirst($trx->status == 1 ? 'completed' : 'pending') }}
                            </span>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-primary editTrxBtn"
                                    data-id="{{ $trx->id }}"
                                    data-amount="{{ $trx->amount }}"
                                    data-token="{{ $trx->token_name }}"
                                    data-type="{{ $trx->type }}"
                                    data-status="{{ $trx->status }}"
                                    data-trxhash="{{ $trx->trx_hash }}">
                                <i class="fas fa-edit"></i> Manage
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center">No transactions found.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $transactions->appends(request()->query())->links('admin.layouts.partials.__pagination') }}
            </div>
        </div>
    </div>

    {{-- ✅ Edit Modal --}}
    <div class="modal fade" id="editTrxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editTrxForm">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit Transaction</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="editTrxId">

                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="text" id="editTrxAmount" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Token Name</label>
                            <input type="text" id="editTrxToken" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <input type="text" id="editTrxType" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="editTrxStatus" class="form-select">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transaction Hash</label>
                            <input type="text" id="editTrxHash" class="form-control">
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

            $(document).on('click', '.editTrxBtn', function () {
                $('#editTrxId').val($(this).data('id'));
                $('#editTrxAmount').val($(this).data('amount'));
                $('#editTrxToken').val($(this).data('token'));
                $('#editTrxType').val($(this).data('type'));
                $('#editTrxStatus').val($(this).data('status'));
                $('#editTrxHash').val($(this).data('trxhash'));

                editModal = new bootstrap.Modal(document.getElementById('editTrxModal'));
                editModal.show();
            });

            $('#editTrxForm').on('submit', function (e) {
                e.preventDefault();

                const id = $('#editTrxId').val();
                const data = {
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT',
                    amount: $('#editTrxAmount').val(),
                    token_name: $('#editTrxToken').val(),
                    type: $('#editTrxType').val(),
                    status: $('#editTrxStatus').val(),
                    trx_hash: $('#editTrxHash').val()
                };

                $.ajax({
                    url: `/admin/transactions/${id}`,
                    type: 'POST',
                    data: data,
                    beforeSend: function () {
                        $('#editTrxForm button[type=submit]').html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
                    },
                    success: function () {
                        $('#editTrxForm button[type=submit]').html('Save Changes').prop('disabled', false);
                        editModal.hide();

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'Transaction updated successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        const row = $(`button[data-id='${id}']`).closest('tr');
                        row.find('.trx-amount').text(data.amount);
                        row.find('.trx-token').text(data.token_name);
                        row.find('.trx-type').text(data.type);

                        const badge = row.find('span.badge');
                        badge.removeClass('bg-success bg-warning bg-danger');
                        if (data.status === 'completed') badge.addClass('bg-success');
                        else if (data.status === 'pending') badge.addClass('bg-warning');
                        else badge.addClass('bg-danger');
                        badge.text(data.status.charAt(0).toUpperCase() + data.status.slice(1));
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Something went wrong. Try again.'
                        });
                        $('#editTrxForm button[type=submit]').html('Save Changes').prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
