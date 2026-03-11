<div style="text-align:center; margin-top:50px; font-family: Arial;">
    <h1>First-Time Password Setup</h1>
    <p>Create your personal login password for Fisher's Pond.</p>

    <form action="processPassword.php" method="POST" onsubmit="submitKioskForm(event, this)" style="display: inline-block; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        
        <input type="number" name="staffID" placeholder="Your Staff ID (e.g., 105)" required 
               style="font-size:18px; padding:10px; width:250px; text-align:center; margin-bottom: 15px; box-sizing: border-box;"><br>
        
        <input type="password" name="newPassword" placeholder="Create a New Password" required 
               style="font-size:18px; padding:10px; width:250px; text-align:center; margin-bottom: 20px; box-sizing: border-box;"><br>
        
        <button type="submit" style="padding:12px 20px; font-size: 16px; background-color: #2980b9; color: white; border: none; cursor: pointer; border-radius: 5px; width: 100%;">
            Hash & Save Password
        </button>
    </form>

    <br><br>
    <button type="button" onclick="loadContent('staffLogIn.php')" style="padding:5px 10px; background: none; border: 1px solid #ccc; cursor: pointer;">
        Back to Login Screen
    </button>
</div>