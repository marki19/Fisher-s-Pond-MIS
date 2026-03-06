<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  
</head>
<body>
  <div class="sidebar">
    <button onclick="loadContent('addEmployee.php')">Employees</button>
    <button onclick="loadContent('products.php')">Products</button>
    <button onclick="loadContent('staff.php')">Staff</button>
  </div>

  <div class="content" id="mainContent">
    <h2>Welcome to the Dashboard</h2>
    <p>Select an option from the sidebar to view content.</p>
  </div>

  <script>
    function loadContent(file) {
      const xhttp = new XMLHttpRequest();
      xhttp.onload = function() {
        document.getElementById("mainContent").innerHTML = this.responseText;
      }
      xhttp.open("GET", file, true);
      xhttp.send();
    }
  </script>

</body>
</html>