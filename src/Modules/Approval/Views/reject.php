<?php /** @var array $approval */ ?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5>Reject Gatepass</h5>
    </div>

    <div class="card-body">

        <p>
            You are rejecting Gatepass 
            <strong>#<?= $approval['gatepass_id'] ?></strong>.
        </p>

<form method="POST" action="/approvals/<?= $approval['approval_id'] ?>/reject">
            <div class="mb-3">
                <label class="form-label">Reason for Rejection *</label>
                <textarea name="comment" 
                          class="form-control" 
                          rows="3"
                          required></textarea>
            </div>

            <button type="submit" class="btn btn-danger">
                Confirm Rejection
            </button>

            <a href="/approvals" class="btn btn-secondary">
                Cancel
            </a>
        </form>

    </div>
</div>