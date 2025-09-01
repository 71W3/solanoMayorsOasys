<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Floating Image Slider</title>
  <style>
    body 
    {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      overflow: hidden;
    }

    .bg1 
    {
      position: relative;
      width: 99%;
      max-width: 99%;
      height: 99%;
      float: center;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
      border-radius: 15px;
      background: url('images/blurry.jpg') center/cover no-repeat;
    }

    .bg 
    {
      background-color: rgba(128, 128, 128, 0.6);
      width: 97.5%;
      max-width: 99%;
      height: 94.4%;
      border-radius: 15px;
      padding: 20px;
      position: relative;
    }

    .slider-container 
    {
      position: absolute;
      top: 35%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 100%;
      max-width: 600px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
      border-radius: 15px;
    }

    .slider-wrapper 
    {
      position: relative;
      width: 100%;
      height: 300px;
      overflow: hidden;
      border-radius: 15px;
      box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
      background: white;
    }

    .slider 
    {
      display: flex;
      transition: transform 0.5s ease-in-out;
    }

    .slide 
    {
      min-width: 100%;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .slide img 
    {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.8);
    }

    button 
    {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      z-index: 10;
    }

    button img 
    {
      width: 40px;
      height: 40px;
    }

    button.prev 
    {
      left: -60px;
    }

    button.next 
    {
      right: -60px;
    }

    button:focus {
      outline: none;
    }

    .donation-section 
    {
      margin-top: 20px;
      text-align: center;
    }

    .donate-button 
    {
      background-color: #ff5722;
      color: white;
      border: none;
      padding: 10px 20px;
      font-size: 1.2rem;
      border-radius: 8px;
      cursor: pointer;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      margin-top: 220px;
      margin-left: -180px;
    }
    .donate-buttons
    {
      background-color: #ff5722;
      color: white;
      border: none;
      padding: 10px 20px;
      font-size: 1.2rem;
      border-radius: 8px;
      cursor: pointer;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      margin-top: 220px;
      margin-left: -60px;
      max-width: 50%
    }
    .donate-button:hover 
    {
      background-color: #e64a19;
    }
    .donate-buttons:hover 
    {
      background-color: #e64a19;
    }
    .slogan
    {
      margin-top: 530px;
      font: white;
    }
    .form-table {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    border-collapse: separate;
    border-spacing: 0 10px;
  }

  .form-table td {
    padding: 10px;
    text-align: left;
  }

  /* Style the input fields */
  .input-field {
    width: 100%;
    padding: 8px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
  }

  /* Style the submit button */
  .submit-btn {
    padding: 10px 20px;
    font-size: 16px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }

  /* Hover effect for the submit button */
  .submit-btn:hover {
    background-color: #45a049;
  }

  /* Style the create account link */
  .create-account-link {
    margin-right: 10px;
    font-size: 14px;
    color: black;
  }

  .create-account-link:hover {
    text-decoration: underline;
    
  }
  .donation-section
  {
    margin-top: 420px;
  }
  .donation-section
  {
    background-color: rgba(169, 169, 169, 0.5);
    max-width: 20%;
    max-height: 30%;
    margin-left: 600px;
    border-radius: 25px;
    box-shadow: 10px 10px 5px lightblue;
  }
  .text
  {
    background-color: lightblue;
  }
  </style>
</head>
<body>
<div class="bg1">
  <div class="bg">
    <div class="slider-container">
      <button class="prev"><img src="images/l.png" alt="Previous"></button>
      <div class="slider-wrapper">
        <div class="slider">
          <div class="slide"><img src="images/bg1.jpg" alt="Image 1"></div>
          <div class="slide"><img src="images/5th.jpg" alt="Image 2"></div>
          <div class="slide"><img src="images/help.jpg" alt="Image 3"></div>
          <div class="slide"><img src="images/4.jpg" alt="Image 4"></div>
          <div class="slide"><img src="images/doc.jpg" alt="Image 5"></div>
        </div>
      </div>
      <button class="next"><img src="images/r.png" alt="Next"></button>
    </div>
    <div class="donation-section">
    <form action="loginFunctions.php" method="post" onsubmit="return confirm('Are you sure you want to login?')">
  <table class="form-table">
    <tr>
      <td><Label class="">Username:</Label></td>
      <td><input type="text" name="username" required class="input-field"></td>
    </tr>
    <tr>
      <td><Label class="">Password:</Label></td>
      <td><input type="password" name="password" required class="input-field"></td>
    </tr>         
    <tr>
      <td></td>
      <td>
        <a href="donors.php" class="create-account-link"><Label class="text">Create Account:</Label></a>
        <input type="submit" name="userlogin" value="Log in" class="submit-btn">
      </td>
    </tr>
  </table>
</form>
      <p class="slogan">This is where your donation goes.</p>
    </div>
  </div>
</div>
  <script>
    const slider = document.querySelector('.slider');
    const slides = document.querySelectorAll('.slide');
    const prevButton = document.querySelector('.prev');
    const nextButton = document.querySelector('.next');

    let currentSlide = 0;
    const totalSlides = slides.length;

    function updateSliderPosition() 
    {
      slider.style.transform = `translateX(-${currentSlide * 100}%)`;
    }

    prevButton.addEventListener('click', () => 
    {
      currentSlide = (currentSlide === 0) ? totalSlides - 1 : currentSlide - 1;
      updateSliderPosition();
    });

    nextButton.addEventListener('click', () => 
    {
      currentSlide = (currentSlide === totalSlides - 1) ? 0 : currentSlide + 1;
      updateSliderPosition();
    });
  </script>
</body>
</html>
