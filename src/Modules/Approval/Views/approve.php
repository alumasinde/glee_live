<?php /** @var array $approval */ ?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5>Approve Gatepass</h5>
    </div>

    <div class="card-body">

        <p>
            Are you sure you want to approve Gatepass 
            <strong>#<?= $approval['gatepass_id'] ?></strong>?
        </p>

<form method="POST" action="/approvals/<?= $approval['approval_id'] ?>/approve">            
            <div class="mb-3">
                <label class="form-label">Comment (Optional)</label>
                <textarea name="comment" 
                          class="form-control" 
                          rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                Confirm Approval
            </button>

            <a href="/approvals" class="btn btn-secondary">
                Cancel
            </a>
        </form>

    </div>
</div>