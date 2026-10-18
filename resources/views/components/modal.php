<div class="modal" id="<?= $id ?>" data-auto-dismiss="<?= $autoDismiss ?? 0 ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= $title ?></h3>
            <button onclick="closeModal('<?= $id ?>')" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <?= $content ?>
        </div>
    </div>
</div>
