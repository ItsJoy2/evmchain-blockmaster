@extends('admin.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">All Payment Jobs</h4>
        </div>

        {{-- 🔍 Filter --}}
        <div class="card-body border-bottom mb-3">
            <form method="GET" action="{{ route('payment_jobs.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User Name</label>
                    <input type="text" name="username" value="{{ request('username') }}" class="form-control" placeholder="Enter user name">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Token Name</label>
                    <input type="text" name="token_name" value="{{ request('token_name') }}" class="form-control" placeholder="Token name">
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
                    <a href="{{ route('payment_jobs.index') }}" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- 📋 Table --}}
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Invoice ID</th>
                    <th>Amount</th>
                    <th>Token</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($paymentJobs as $index => $job)
                    <tr>
                        <td>{{ $index + $paymentJobs->firstItem() }}</td>
                        <td>{{ $job->user->name ?? 'N/A' }}</td>
                        <td>{{ $job->invoice_id }}</td>
                        <td>{{ $job->amount }}</td>
                        <td>{{ strtoupper($job->token_name) }}</td>
                        <td>{{ ucfirst($job->type) }}</td>
                        <td>
                        <span class="badge
                            @if($job->status == 'completed') bg-success
                            @elseif($job->status == 'processing') bg-info
                            @elseif($job->status == 'expired') bg-danger
                            @else bg-warning @endif">
                            {{ ucfirst($job->status) }}
                        </span>
                        </td>
                        <td>{{ $job->created_at->format('Y-m-d') }}</td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-primary editJobBtn"
                                    data-id="{{ $job->id }}"
                                    data-status="{{ $job->status }}"
                                    data-txhash="{{ $job->tx_hash }}">
                                <i class="fas fa-edit"></i> Manage
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center">No payment jobs found.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $paymentJobs->appends(request()->query())->links('admin.layouts.partials.__pagination') }}
            </div>
        </div>
    </div>

    {{-- ✏️ Edit Modal --}}
    <div class="modal fade" id="editJobModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editJobForm">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit Payment Job</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editJobId">
                        <div class="mb-3">
                            <label class="form-label">Transaction Hash</label>
                            <input type="text" id="editJobTxHash" class="form-control" placeholder="Enter TX Hash">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="editJobStatus" class="form-select">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="expired">Expired</option>
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

    {{-- 💡 Script --}}
    <script>
        $(document).ready(function(){
            let modal;

            $(document).on('click', '.editJobBtn', function(){
                $('#editJobId').val($(this).data('id'));
                $('#editJobStatus').val($(this).data('status'));
                $('#editJobTxHash').val($(this).data('txhash'));
                modal = new bootstrap.Modal($('#editJobModal'));
                modal.show();
            });

            $('#editJobForm').on('submit', function(e){
                e.preventDefault();
                const id = $('#editJobId').val();
                const data = {
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT',
                    tx_hash: $('#editJobTxHash').val(),
                    status: $('#editJobStatus').val(),
                };

                $.ajax({
                    url: `/admin/payment-jobs/${id}`,
                    type: 'POST',
                    data: data,
                    beforeSend: function(){
                        $('#editJobForm button[type=submit]').html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
                    },
                    success: function(){
                        modal.hide();
                        Swal.fire({ icon: 'success', title: 'Updated!', text: 'Payment job updated successfully.', timer: 2000, showConfirmButton: false });
                        setTimeout(() => location.reload(), 1200);
                    },
                    error: function(){
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong.' });
                    },
                    complete: function(){
                        $('#editJobForm button[type=submit]').html('Save Changes').prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
