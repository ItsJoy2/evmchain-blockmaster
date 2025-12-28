@extends('admin.layouts.app')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Token List</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTokenModal">+ Add Token</button>
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
                    <th>Symbol</th>
                    <th>Contract</th>
                    <th>Chain</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tokens as $token)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            @if($token->icon)
                                <img src="{{ asset('uploads/token_icons/' . $token->icon) }}" alt="icon" width="32" height="32" class="rounded">
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ $token->token_name }}</td>
                        <td>{{ $token->symbol }}</td>
                        <td>{{ Str::limit($token->contract_address, 15) }}</td>
                        <td>{{ $token->chain->chain_name ?? 'N/A' }}</td>
                        <td>
                            @if($token->status)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editTokenModal{{ $token->id }}">Edit</button>
                            <form action="{{ route('token.destroy', $token->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Delete token?')">Delete</button>
                            </form>
                        </td>
                    </tr>

                    <!-- Edit Token Modal -->
                    <div class="modal fade" id="editTokenModal{{ $token->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('token.update', $token->id) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Token</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label>Chain</label>
                                            <select name="chain_id" class="form-select" required>
                                                @foreach($chains as $chain)
                                                    <option value="{{ $chain->id }}" {{ $chain->id == $token->chain_id ? 'selected' : '' }}>
                                                        {{ $chain->chain_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label>Token Name</label>
                                            <input type="text" name="token_name" value="{{ $token->token_name }}" class="form-control" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Symbol</label>
                                            <input type="text" name="symbol" value="{{ $token->symbol }}" class="form-control" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Contract Address</label>
                                            <input type="text" name="contract_address" value="{{ $token->contract_address }}" class="form-control" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Icon</label>
                                            <input type="file" name="icon" class="form-control" id="editTokenIcon{{ $token->id }}" onchange="previewEditTokenIcon({{ $token->id }})">
                                        </div>
                                        <img id="editTokenIconPreview{{ $token->id }}"
                                             src="{{ $token->icon ? asset('uploads/token_icons/' . $token->icon) : '' }}"
                                             alt="icon preview" class="img-fluid rounded mb-2" style="width:50px; height:50px; {{ $token->icon ? '' : 'display:none;' }}">
                                        <div class="form-check mt-2">
                                            <input type="checkbox" name="status" class="form-check-input" {{ $token->status ? 'checked' : '' }}>
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

            {{ $tokens->links() }}
        </div>
    </div>
</div>

<!-- Add Token Modal -->
<div class="modal fade" id="addTokenModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('token.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Token</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Chain</label>
                        <select name="chain_id" class="form-select" required>
                            @foreach($chains as $chain)
                                <option value="{{ $chain->id }}">{{ $chain->chain_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Token Name</label>
                        <input type="text" name="token_name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Symbol</label>
                        <input type="text" name="symbol" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Contract Address</label>
                        <input type="text" name="contract_address" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Icon</label>
                        <input type="file" name="icon" class="form-control" id="addTokenIcon" onchange="previewAddTokenIcon()" required>
                    </div>
                    <img id="addTokenIconPreview" src="" alt="icon preview" class="img-fluid rounded mb-2" style="width:50px; height:50px; display:none;">
                    <div class="form-check mt-2">
                        <input type="checkbox" name="status" class="form-check-input">
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Token</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JS for modal preview -->
<script>
    function previewAddTokenIcon() {
        const file = document.getElementById('addTokenIcon').files[0];
        const preview = document.getElementById('addTokenIconPreview');
        if(file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'inline-block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    }

    function previewEditTokenIcon(id) {
        const file = document.getElementById('editTokenIcon' + id).files[0];
        const preview = document.getElementById('editTokenIconPreview' + id);
        if(file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'inline-block';
        } else if(preview.src) {
            preview.style.display = 'inline-block';
        } else {
            preview.style.display = 'none';
        }
    }
</script>
@endsection
