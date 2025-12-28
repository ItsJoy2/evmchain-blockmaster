@extends('admin.layouts.app')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Chain List</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addChainModal">+ Add Chain</button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Chain ID</th>
                    <th>RPC URL</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach($chains as $chain)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            @if($chain->icon)
                                <img src="{{ asset('uploads/chain_icons/' . $chain->icon) }}" alt="icon" width="32" height="32" class="rounded">
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ $chain->chain_name }}</td>
                        <td>{{ $chain->chain_id }}</td>
                        <td>{{ Str::limit($chain->chain_rpc_url, 25) ?? 'N/A' }}</td>
                        <td>
                            @if($chain->status)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                    data-bs-target="#editChainModal{{ $chain->id }}">Edit</button>
                            <form action="{{ route('chain.destroy', $chain->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Delete chain?')">Delete</button>
                            </form>
                        </td>
                    </tr>

                    <!-- Edit Chain Modal -->
                    <div class="modal fade" id="editChainModal{{ $chain->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('chain.update', $chain->id) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Chain</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label>Chain Name</label>
                                            <input type="text" name="chain_name" value="{{ $chain->chain_name }}" class="form-control" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Chain ID</label>
                                            <input type="text" name="chain_id" value="{{ $chain->chain_id }}" class="form-control" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>RPC URL</label>
                                            <input type="text" name="chain_rpc_url" value="{{ $chain->chain_rpc_url }}" class="form-control">
                                        </div>
                                        <div class="mb-2">
                                            <label>Icon</label>
                                            <input type="file" name="icon" class="form-control" id="editIcon{{ $chain->id }}" onchange="previewEditIcon({{ $chain->id }})">
                                        </div>
                                        <img id="editIconPreview{{ $chain->id }}" src="{{ $chain->icon ? asset('uploads/chain_icons/' . $chain->icon) : '' }}"
                                             alt="icon preview" class="img-fluid rounded mb-2" style="width:50px; height:50px; {{ $chain->icon ? '' : 'display:none;' }}">
                                        <div class="form-check mt-2">
                                            <input type="checkbox" name="status" class="form-check-input" {{ $chain->status ? 'checked' : '' }}>
                                            <label class="form-check-label">Active</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-success">Update</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table>

            {{ $chains->links() }}
        </div>
    </div>
</div>

<!-- Add Chain Modal -->
<div class="modal fade" id="addChainModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('chain.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Chain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Chain Name</label>
                        <input type="text" name="chain_name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Chain ID</label>
                        <input type="text" name="chain_id" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>RPC URL</label>
                        <input type="text" name="chain_rpc_url" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Icon</label>
                        <input type="file" name="icon" class="form-control" id="addIcon" onchange="previewAddIcon()">
                    </div>
                    <img id="addIconPreview" src="" alt="icon preview" class="img-fluid rounded mb-2" style="width:50px; height:50px; display:none;">
                    <div class="form-check mt-2">
                        <input type="checkbox" name="status" class="form-check-input" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Chain</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JS for preview -->
<script>
    function previewAddIcon() {
        const file = document.getElementById('addIcon').files[0];
        const preview = document.getElementById('addIconPreview');
        if(file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'inline-block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    }

    function previewEditIcon(id) {
        const file = document.getElementById('editIcon' + id).files[0];
        const preview = document.getElementById('editIconPreview' + id);
        if(file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'inline-block';
        }
    }
</script>
@endsection
