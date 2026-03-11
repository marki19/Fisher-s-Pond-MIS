<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="style.css" rel="stylesheet">
</head>

<body>
    <div class="sidebar">
        <button onclick="loadContent('adminLogIn.php')">Admin</button>
        <button onclick="loadContent('staffLogIn.php')">Attendance</button>
        <button onclick="loadContent('staffDisplay.php')">Staff</button>
        <button onclick="loadContent('employeeShiftDisplay.php')">Shifts</button>
    </div>

    <div class="content" id="mainContent">
        <h2>Welcome to the Dashboard</h2>
        <p>Select an option from the sidebar to view content.</p>
    </div>

    <script>
       // Function 1: Handles standard sidebar button clicks (GET requests)
function loadContent(file) {
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        document.getElementById("mainContent").innerHTML = this.responseText;
    }
    xhttp.open("GET", file, true);
    // NEW: The Secret Handshake
    xhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest"); 
    xhttp.send();
}

// Function 2: Handles form submissions inside the dashboard (POST requests)
function submitKioskForm(event, formElement) {
    event.preventDefault();
    const formData = new FormData(formElement);

    if (event.submitter && event.submitter.name) {
        formData.append(event.submitter.name, event.submitter.value);
    }

    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        document.getElementById("mainContent").innerHTML = this.responseText;
    }
    xhttp.open("POST", formElement.getAttribute("action"), true);
    // NEW: The Secret Handshake
    xhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhttp.send(formData);
}
    </script>

</body>

</html>