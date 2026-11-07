/* Staff scanner: camera capture feeding the existing scan form. */
(function () {
    'use strict';

    var reader = document.getElementById('qr-reader');
    var input = document.getElementById('gatepass_number');
    var startBtn = document.getElementById('btn-camera-scan');
    var stopBtn = document.getElementById('btn-camera-stop');

    if (!reader || !input || !startBtn || !stopBtn) return;

    var form = input.closest('form');
    var scanner = null;

    function resetButtons() {
        reader.classList.add('u-d-none');
        stopBtn.classList.add('u-d-none');
        startBtn.classList.remove('u-d-none');
    }

    startBtn.addEventListener('click', function () {
        if (!window.Html5Qrcode) {
            window.alert('Camera library failed to load. Use manual entry.');
            return;
        }

        reader.classList.remove('u-d-none');
        startBtn.classList.add('u-d-none');
        stopBtn.classList.remove('u-d-none');

        scanner = new window.Html5Qrcode('qr-reader');

        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 250 },
            function (decodedText) {
                scanner.stop().catch(function () {});
                resetButtons();
                input.value = decodedText;
                form.submit();
            },
            function () {}
        ).catch(function (err) {
            window.alert('Could not start camera: ' + err);
            resetButtons();
        });
    });

    stopBtn.addEventListener('click', function () {
        if (scanner) {
            scanner.stop().catch(function () {});
        }
        resetButtons();
    });
})();
