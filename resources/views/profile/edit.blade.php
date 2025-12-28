@extends('admin.layouts.app')

@section('title', 'Profile Settings')

@section('content')
    <div class="container py-5" style="max-width: 900px;">
        <div class="text-center mb-5">
            <h2 style="font-weight: 600; color: #333;">Profile Settings</h2>
            <p style="color: #666;">Manage your profile information and update your password securely.</p>
        </div>

        {{-- Profile Update Section --}}
        <div class="card shadow-sm mb-4" style="border: none; border-radius: 10px;">
            <div class="card-header text-white"
                 style="background: linear-gradient(90deg, #007bff, #0056b3); border-radius: 10px 10px 0 0;">
                <h5 class="mb-0" style="font-weight: 500;">Update Profile Information</h5>
            </div>
            <div class="card-body" style="padding: 30px;">
                {{-- Include profile update form --}}
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Name</label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name', auth()->user()->name) }}"
                               style="border-radius: 8px; border: 1px solid #ccc; padding: 10px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', auth()->user()->email) }}"
                               style="border-radius: 8px; border: 1px solid #ccc; padding: 10px;">
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary"
                                style="border-radius: 8px; padding: 10px 25px; font-weight: 500;">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Password Update Section --}}
        <div class="card shadow-sm" style="border: none; border-radius: 10px;">
            <div class="card-header text-white"
                 style="background: linear-gradient(90deg, #28a745, #1e7e34); border-radius: 10px 10px 0 0;">
                <h5 class="mb-0" style="font-weight: 500;">Update Password</h5>
            </div>
            <div class="card-body" style="padding: 30px;">
                {{-- Include password update form --}}
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Current Password</label>
                        <input type="password" name="current_password" class="form-control"
                               style="border-radius: 8px; border: 1px solid #ccc; padding: 10px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">New Password</label>
                        <input type="password" name="password" class="form-control"
                               style="border-radius: 8px; border: 1px solid #ccc; padding: 10px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control"
                               style="border-radius: 8px; border: 1px solid #ccc; padding: 10px;">
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-success"
                                style="border-radius: 8px; padding: 10px 25px; font-weight: 500;">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
