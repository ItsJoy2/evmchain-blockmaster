<div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="updateUserForm" method="POST">
            @csrf
            @method('PUT')

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update User and Wallet Status</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modal_user_id">

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" id="modal_user_name" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" id="modal_user_email" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label>User Status</label>
                        <select id="modal_block_status" name="is_block" class="form-control">
                            <option value="0">Unblock</option>
                            <option value="1">Block</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
