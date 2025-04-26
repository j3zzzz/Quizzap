<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture with Upload Icon</title>
    <style>
        .profile-container {
            position: relative;
            display: inline-block;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ccc;
        }

        .upload-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: white;
            border-radius: 50%;
            padding: 5px;
            border: 1px solid #ccc;
            cursor: pointer;
        }

        .upload-icon img {
            width: 24px;
            height: 24px;
        }

        /* Hide the file input */
        #file-input {
            display: none;
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <!-- Profile Picture -->
        <img id="profile-pic" src="default_profile.jpg" alt="Profile Picture" class="profile-picture">

        <!-- Camera Icon Overlay as Button -->
        <label for="file-input" class="upload-icon">
            <img src="icon.png" alt="Upload Icon">
        </label>
        <!-- Hidden file input -->
        <input type="file" id="file-input" accept="image/*" onchange="uploadImage(event)">
    </div>

    <script>
        function uploadImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Update the profile picture with the selected image
                    document.getElementById('profile-pic').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    </script>

</body>
</html>
