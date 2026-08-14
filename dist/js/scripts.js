document.addEventListener("DOMContentLoaded", function() {
    console.log("JavaScript is running"); // Add this line to check if script is running
    const text = "You are welcome to the NIS Identification Card Management System";
    let index = 0;

    function typeText() {
        if (index < text.length) {
            document.getElementById("animatedText").innerHTML += text.charAt(index);
            index++;
            setTimeout(typeText, 100);
        }
    }

    typeText();
});

$(document).ready(function() {
    // Your custom JavaScript for handling interactions
    $('.collapse-item').on('click', function() {
        // Additional logic to load the pages dynamically if needed
    });
});
