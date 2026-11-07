<?php use App\Core\View; ?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-badge"></i> Gatepass Types
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Settings
        </a>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($types),
        'noun'  => 'gatepass type',
    ]); ?>

    <?php if (empty($types)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-id-badge"></i>
            <div class="empty-state-title">No gatepass types found</div>
            <div class="empty-state-text">
                Types decide which gate actions a pass allows, and whether new
                passes of that type start out returnable.
            </div>
        </div>
    <?php else: ?>

        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Return scan</th>
                    <th>Returnable by default</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php
                // Matches assertTypeActionAllowed(): no config means the gate
                // allows everything, so say that instead of implying a block.
                $actionBadge = function (bool $allowed, bool $configured): string {
                    if (!$configured) {
                        return '<span class="badge badge-secondary">Allowed · unset</span>';
                    }

                    return $allowed
                        ? '<span class="badge badge-success">Enabled</span>'
                        : '<span class="badge badge-danger">Disabled</span>';
                };
                ?>
                <?php foreach ($types as $type):
                    $configured = $type->hasActionConfig();
                    $checkin  = $type->allowsAction('checkin');
                    $checkout = $type->allowsAction('checkout');
                    $return   = $type->allowsAction('return');

                    // SAFE JS encoding (important fix)
                    $safeName = htmlspecialchars(json_encode($type->name), ENT_QUOTES, 'UTF-8');
                ?>
                    <tr data-id="<?= (int)$type->id ?>">

                        <td><strong><?= htmlspecialchars($type->name) ?></strong></td>
                        <td><code><?= htmlspecialchars($type->code ?? '—') ?></code></td>

                        <td><?= $actionBadge($checkin, $configured) ?></td>

                        <td><?= $actionBadge($checkout, $configured) ?></td>

                        <td><?= $actionBadge($return, $configured) ?></td>

                        <td>
                            <?= $type->isReturnable
                                ? '<span class="badge badge-info">Yes</span>'
                                : '<span class="badge badge-secondary">No</span>' ?>
                        </td>

                        <td class="text-right">
                            <button
                                class="btn btn-secondary btn-sm"
                                data-open-form="#modal"
                                data-open-title="Configure: <?= htmlspecialchars($type->name, ENT_QUOTES) ?>"
                                data-field-id="<?= (int) $type->id ?>"
                                data-field-checkin="<?= $checkin ? '1' : '0' ?>"
                                data-field-checkout="<?= $checkout ? '1' : '0' ?>"
                                data-field-return="<?= $return ? '1' : '0' ?>"
                                data-field-returnable="<?= $type->isReturnable ? '1' : '0' ?>"
                            >
                                Configure
                            </button>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>
</div>

<!-- MODAL -->
<div id="modal" class="modal">
    <div class="modal-content">

        <div class="modal-header">
            <h3 id="modalTitle" data-modal-title>Configure</h3>
            <button type="button" data-close="#modal" aria-label="Close">&times;</button>
        </div>

        <div class="modal-body">
            <input type="hidden" name="id">
            <div class="form-group">
                <label>
                    <input type="checkbox" id="checkin" name="checkin">
                    Allow Check-in
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="checkout" name="checkout">
                    Allow Check-out
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="return" name="return">
                    Allow Return scan
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="returnable" name="returnable">
                    Returnable by default
                </label>
                <p class="form-text">
                    New gatepasses of this type start with the Returnable box ticked.
                    The requester can still change it, and a returnable pass needs an
                    expected return date.
                </p>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close="#modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="gt-save">Save</button>
        </div>

    </div>
</div>

<script src="/assets/js/pages/gatepass-types.js" defer></script>
