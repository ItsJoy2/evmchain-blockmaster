@extends('admin.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">All Packages</h4>
            <button class="btn btn-success btn-sm" id="addPackageBtn">Add Package</button>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($packages as $index => $package)
                    <tr data-id="{{ $package->id }}">
                        <td>{{ $index + $packages->firstItem() }}</td>
                        <td class="package-name">{{ $package->name }}</td>
                        <td class="package-price">{{ $package->price }}</td>
                        <td class="package-duration">{{ $package->duration }}</td>
                        <td>
                            <span class="badge {{ $package->status ? 'bg-success' : 'bg-danger' }}">
                                {{ $package->status ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary editPackageBtn"
                                    data-id="{{ $package->id }}"
                                    data-name="{{ $package->name }}"
                                    data-price="{{ $package->price }}"
                                    data-duration="{{ $package->duration }}"
                                    data-status="{{ $package->status }}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger deletePackageBtn" data-id="{{ $package->id }}">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No packages found.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $packages->links('admin.layouts.partials.__pagination') }}
            </div>
        </div>
    </div>

    <!-- ✅ Modal -->
    <div class="modal fade" id="packageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="packageForm">
                @csrf
                <input type="hidden" id="packageId">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add/Edit Package</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="packageName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" id="packagePrice" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration</label>
                            <input type="text" id="packageDuration" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="packageStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Package</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ✅ AJAX Script --}}
    <script>
        $(document).ready(function () {
            let packageModal = new bootstrap.Modal(document.getElementById('packageModal'));

            // 🟢 Open Add Modal
            $('#addPackageBtn').click(function () {
                $('#packageForm')[0].reset();
                $('#packageId').val('');
                packageModal.show();
            });

            // 🟢 Open Edit Modal
            $(document).on('click', '.editPackageBtn', function () {
                const btn = $(this);
                $('#packageId').val(btn.data('id'));
                $('#packageName').val(btn.data('name'));
                $('#packagePrice').val(btn.data('price'));
                $('#packageDuration').val(btn.data('duration'));
                $('#packageStatus').val(btn.data('status'));
                packageModal.show();
            });

            // 🟢 Submit Add/Edit
            $('#packageForm').submit(function (e) {
                e.preventDefault();
                const id = $('#packageId').val();
                const formData = {
                    _token: '{{ csrf_token() }}',
                    name: $('#packageName').val(),
                    price: $('#packagePrice').val(),
                    duration: $('#packageDuration').val(),
                    status: $('#packageStatus').val()
                };
                let url = id ? `/admin/packages/${id}` : `/admin/packages`;
                $.ajax({
                    url: url,
                    type: id ? 'PUT' : 'POST',
                    data: formData,
                    success: function (res) {
                        packageModal.hide();
                        Swal.fire('Success', 'Package saved successfully!', 'success').then(()=>{
                            location.reload();
                        });
                    },
                    error: function (xhr) {
                        let msg = 'Something went wrong!';
                        if(xhr.responseJSON && xhr.responseJSON.errors){
                            msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        }
                        Swal.fire('Error', msg, 'error');
                    }
                });
            });

            // 🟢 Delete Package
            $(document).on('click', '.deletePackageBtn', function () {
                const id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will delete the package!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete!'
                }).then((result) => {
                    if(result.isConfirmed){
                        $.ajax({
                            url: `/admin/packages/${id}`,
                            type: 'DELETE',
                            data: {_token: '{{ csrf_token() }}'},
                            success: function () {
                                Swal.fire('Deleted!', 'Package deleted.', 'success').then(()=>{
                                    location.reload();
                                });
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
