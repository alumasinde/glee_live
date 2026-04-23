<h1 class="page-heading mb-4">
    <i class="fa-solid fa-user-pen"></i> Edit User
</h1>

<div class="card shadow-sm">
    <div class="card-body">

<form method="POST" action="/settings/users/<?= $userData['id'] ?>">
    
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text"
                           name="first_name"
                           class="form-control"
                           value="<?= htmlspecialchars($userData['first_name']) ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text"
                           name="last_name"
                           class="form-control"
                           value="<?= htmlspecialchars($userData['last_name']) ?>"
                           required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text"
                       name="username"
                       class="form-control"
                       value="<?= htmlspecialchars($userData['username']) ?>"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email"
                       name="email"
                       class="form-control"
                       value="<?= htmlspecialchars($userData['email']) ?>"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label">Assign Roles</label>
                <?php foreach ($roles as $role): ?>
                    <div class="form-check">
                        <input type="checkbox"
                               name="roles[]"
                               value="<?= $role['id'] ?>"
                               class="form-check-input"
                               id="role-<?= $role['id'] ?>"
                               <?= in_array($role['id'], $userRoles) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="role-<?= $role['id'] ?>">
                            <?= htmlspecialchars($role['name']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-end">
                <a href="/settings/users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Update User
                </button>
            </div>

        </form>

    </div>
</div>