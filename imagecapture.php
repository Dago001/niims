<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Capture Form</title>
    <style>
        #camera, #photoPreview {
            border: 2px solid #ccc;
            border-radius: 8px;
            width: 320px;
            height: 240px;
        }
    </style>
</head>
<body>
    <h1>Capture User Photo</h1>
    <form id="captureForm" method="post" action="save_photo" >
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
        <br><br>

        <div>
            <video id="camera" autoplay></video>
            <canvas id="photoPreview" style="display: none;"></canvas>
        </div>
        <br>
        <button type="button" id="captureBtn">Capture Photo</button>
        <button type="submit">Save Photo</button>
    </form>

    <script>
        const camera = document.getElementById('camera');
        const photoPreview = document.getElementById('photoPreview');
        const captureBtn = document.getElementById('captureBtn');
        const form = document.getElementById('captureForm');
        let capturedImage = null;

        // Access the webcam
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                camera.srcObject = stream;
            })
            .catch(err => {
                alert("Error accessing the camera: " + err.message);
            });

        // Capture photo on button click
        captureBtn.addEventListener('click', () => {
            const canvas = photoPreview;
            const context = canvas.getContext('2d');
            canvas.width = camera.videoWidth;
            canvas.height = camera.videoHeight;
            context.drawImage(camera, 0, 0, canvas.width, canvas.height);

            capturedImage = canvas.toDataURL('image/png'); // Convert to Base64
            alert("Photo captured! You can now save it.");
        });

        // Submit the form
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;

            if (!username || !capturedImage) {
                alert("Please enter a username and capture a photo before saving.");
                return;
            }

            // Send data to the server via AJAX
            fetch('save_photo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    image: capturedImage,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(err => alert("An error occurred: " + err.message));
        });
    </script>
</body>
</html>
