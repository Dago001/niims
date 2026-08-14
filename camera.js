// Get the modal and canvas elements
const modal = document.getElementById('image-capture-modal');
const canvas = document.getElementById('image-capture-canvas');
const ctx = canvas.getContext('2d');

// Get the image capture button and save image button
const captureBtn = document.getElementById('image-capture-btn');
const saveImageBtn = document.getElementById('save-image-btn');

// Get the image name input field
const imageNameInput = document.getElementById('image-name');

// Set up the camera stream
let stream;

// Set up the event listeners
captureBtn.addEventListener('click', captureImage);
saveImageBtn.addEventListener('click', saveImage);

// Function to capture the image
function captureImage() {
  // Get the camera stream
  navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
      // Set up the video stream
      const video = document.createElement('video');
      video.srcObject = stream;
      video.play();

      // Draw the video stream on the canvas
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    })
    .catch(error => console.error('Error capturing image:', error));
}

// Function to save the image
function saveImage() {
  // Get the image data from the canvas
  const imageData = canvas.toDataURL();

  // Get the image name from the input field
  const imageName = imageNameInput.value;

  // Save the image to the server using AJAX
  fetch('/save-image', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      image_data: imageData,
      image_name: imageName
    })
  })
  .then(response => response.text())
  .then(message => console.log(message))
  .catch(error => console.error('Error saving image:', error));
}

