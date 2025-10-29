<style>
		html {
			scroll-behavior: smooth;
		}

		h2,
		h3,
		h4,
		h5,
		h6 {
			font-weight: 700;
		}

		body {
			/* font-family: 'Open Sans', sans-serif; */
			font-family: 'Lato', sans-serif;
			/* font-family: 'Poppins', sans-serif; */
		}

		input, textarea{
			font-family: 'Lato', sans-serif;
			background-color: #F1F0F7;
			border: 0;
			border-radius: 2px;
			padding: 3px 10px;
		}

		input:focus, textarea:focus{
			outline: none;
		}

		/* .wrapper {
			background: #fff;
			margin-bottom: 270px;
			box-shadow: 0px 25px 10px -15px rgba(0,0,0,0.08); 
		} */

		.footer{
			padding: 10px 0px;
			background: white;
			box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.1);
			text-align: left;
		}

		.navbar {
			padding: 10px 0px;
			background: white;
			box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
		}

		.navbar .nav-item {
			margin: 0 .75rem 0;
			display: flex;
    		align-items: center;
		}

		.navbar-brand a {
			box-shadow: 0px 25px 10px -15px rgba(0, 0, 0, 0.08);
		}

		.nav-dropdown {
			border-radius: 10px;
			border: 0;
			padding: 0 1.2rem;
			background: linear-gradient(to right, #8914fe 0%, #8063f5 100%);
			box-shadow: 0px 25px 10px -10px rgba(0, 0, 0, 0.08);
		}

		.nav-dropdown a.dropdown-link {
			color: #f5f5f5 !important;
		}

		.btn-primary {
			color: #fff;
			background: linear-gradient(to right, #8914fe 0%, #8063f5 100%) !important;
			border-color: #6F42C2 !important;
		}

		.btn-primary:hover {
			color: #fff;
			background-color: #906BD4 !important;
			border-color: #906BD4 !important;
			-webkit-box-shadow: none;
			box-shadow: none;
		}

		.btn-primary:focus {
			box-shadow: 0 0 0 0.2rem rgba(111, 66, 194, .5) !important;
		}

		.jumbotron {
			/* padding: 18rem 0; */
			padding: 20% 0;
			background: url('./assets/img/background.jpg');
			background-repeat: no-repeat;
			background-position: 100% 10%;
		}

		.jumbotron-title {
			font-size: 3rem;
			text-transform: capitalize;
		}

		section {
			padding: 3rem 0 6rem;
		}

		.section-title {
			margin-bottom: 6rem;
		}

		section img {
			margin-bottom: 2rem;
		}

		.feature-section {
			background: url('./assets/img/getty_patient_care.jpg');
			background-position: 0% 100%;
			background-size: 600px;
			background-repeat: no-repeat;
		}

		.feature-section .card {
			border-radius: 10px;
			box-shadow: 0px 25px 10px -15px rgba(0, 0, 0, 0.08);
			transition: .4s;
		}

		.feature-section .card:hover {
			transform: scale(1.1);
			box-shadow: 0px 25px 10px -15px rgba(0, 0, 0, 0.08);
		}

		.footer {
			width: 100%;
			height: auto;
			color: #333;
			bottom: 0px;
			left: 0px;
			padding: 45px 0 40px;
		}

		.footer a {
			color: #333;
		}

		.footer a:hover {
			color: purple;
			text-decoration: none;
		}

		.footer ul li {
			margin: .8rem 0;
		}

		.upper-footer {
			border-bottom: #f8f8f9;
			width: 100%;
		}

		.bottom-footer {
			margin-top: 10px;
		}

		.footer ul {
			list-style-type: none;
		}

		.footer ul li {
			margin-left: -40px;
		}

		.footer-link {
			text-align: right;
		}

		.bottom-footer-link {
			margin: 0 5px;
		}

		.top-button {
			position: absolute;
			right: 30px;
		}

		.top-scroll {
			padding: 10px 16px;
			background-color: #f2f2f2;
			border-radius: 5px;
			font-size: 20px;
			transition: .3s;
		}

		.top-scroll:hover {
			background-color: #dfdddd;
		}

		img.banner {
			width: 380px !important;
			height: 450px !important;
		}

		button:focus{
			outline: none;
		}

		body.booking-page, body.appointment-page{
		background: #f8f7fc;
		text-align: center;
		}
		.calendar {
		display: inline-block;
		background: #fff;
		padding: 1em;
		border-bottom-right-radius: 10px;
		border-bottom-left-radius: 10px;
		box-shadow: 0 4px 10px rgba(0,0,0,0.1);
		width: 80%;
		}
		.calendar-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 10px;
		height: 70px;
		font-size: 30px;
		}
		.calendar-header button {
		padding: 0.5em 1em;
		font-size: 1rem;
		border: 0px;
		background-color:  #6F42C2 !important;
		color: white;
		border-radius: 4px;
		margin: 4px;
		transition: 0.2s;
		}
		.calendar-header button:hover {
		border: 0;
		background-color:rgb(154, 94, 250) !important;
		transform: scale(1.05);
		}
		.month-year {
		font-size: 1.5em;
		font-weight: bold;
		}
		table {
		width: 100%;
		border-collapse: collapse;
		border: 5px solid white;
		font-family: 'Lato', sans-serif;
		}
		th {
		background-color: #6F42C2 !important;
		height: 30px !important;
		color: white;
		}
		th, td {
		width: 14.2%;
		height: 70px;
		border: 1px solid #ddd;
		text-align: center;
		vertical-align: middle;
		border: 7px solid white;
		}
		td.cell-date {
		font-size: 1rem;
		background-color:#F1F0F7;
		text-align: right;
		vertical-align: bottom;
		font-size: 18px;
		padding: 3px 6px;
		color: #666;
		transition:0.15s;
		pointer-events: all;
		}
		td {
		font-size: 1rem;
		color: white;
		background-color:white;
		pointer-events: none;
		cursor: default;
		}
		td:hover {
			margin: 15px;
			background-color:#E1DFED;
			color: black;
			cursor: pointer;
		}
		td span.today {
			color: white;
			border-radius: 50%;
			width: 25px;
			height: 25px;
			margin: auto;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-weight: bold;
			background-color: #6F42C2 !important;
		}


		td.past-date {
			color: white;
			background-color:rgb(220, 217, 231);
			pointer-events: none;
			cursor: default;
		}

		#popupBox {
			position: fixed;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background: white;
			padding: 20px 0px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
			border-radius: 10px;
			z-index: 9999;
			width: 750px;
			text-align: center;
			display: none;
		}

		#popupContent {
			position: relative;
			display: flex;
			justify-content: space-evenly;
		}

		#closePopup {
			position: absolute;
			top: 5px;
    		right: 25px;
			cursor: pointer;
			font-size: 34px;
			color: #aaa;
			transition: 0.2s;
		}

		#closePopup:hover {
			color: #333;
			transform: scale(1.2);
		}

		#overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.5);
			z-index: 9998;
			opacity: 0;
			visibility: hidden;
			transition: opacity 0.15s ease;
		}
		#overlay.active {
			opacity: 1;
			visibility: visible;
		}

		.daily-schedule{
			width: 55%;
		}

		.daily-schedule td {
			font-size: 1rem;
			background-color:#F1F0F7;
			text-align: right;
			vertical-align: bottom;
			font-size: 18px;
			padding: 3px 6px;
			color: #666;
			transition:0.15s;
			pointer-events: all;
		}
		.daily-schedule td:hover {
			margin: 15px;
			background-color:#E1DFED;
			color: black;
			cursor: pointer;
		}

		#appointmentForm{
			width: 40%;
			margin: 4px;
		}

		.slot.selected-slot {
			background-color: #95c97f;
			color: white;
		}

		.slot.selected-slot:hover {
			background-color:#76ab60;
			color: white;
		}

		#popupDate, #appointmentTimeInput{
			pointer-events: none;
		}
		
		.input-container{
			display: flex;
			justify-content: space-between;
			margin-bottom: 5px;
		}

		.input-container span{
			font-weight: bold;
		}
		
		#appointmentDescriptionInput{
			pointer-events: all !important;
			resize: none;
			height: 60px;
		}

		#confirmDate{
			padding: 0.5em 1em;
			font-size: 1rem;
			border: 0px;
			background-color:  #6F42C2 !important;
			color: white;
			border-radius: 4px;
			margin: 0px;
			margin-top: 5px;
			transition: 0.2s;
			width: 100%;
			font-weight:bold;
			font-family: 'Lato', sans-serif;
		}

		#confirmDate:hover {
			border: 0;
			background-color:rgb(154, 94, 250) !important;
			transform: scale(1.015);
		}

		.booked-slot {
			background-color: #f5c6cb !important;
			color: #721c24 !important;
			pointer-events: none !important;
		}

		#appointmentList{
			display: none;
		}

		.logged-in-message{
			font-weight: bold;
			display: flex;
    		align-items: center;
		}

		.logged-in-message img{
			margin-left: 5px;
		}

		.slot.scheduled-slot {
			background-color: #95c97f !important;
			color: white;
		}

		#confirmDate.disabled_appointment {
            background-color: #adacb3 !important;
            pointer-events: none;
        }

		.scheduled-date {
			background-color: #95c97f !important;
			color: white !important;
		}

		.modal-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 1000;
			opacity: 0;
			visibility: hidden;
			transition: opacity 0.3s ease, visibility 0.3s ease;
		}

		.modal-overlay.active {
			opacity: 1;
			visibility: visible;
		}

		.modal-box {
			background: #fff;
			padding: 20px 30px;
			border-radius: 10px;
			text-align: center;
		}

		.modal-actions {
			margin-top: 20px;
		}

		.modal-actions button {
			margin: 0 10px;
			padding: 8px 16px;
		}

		.appointment-page .cancel_appointment {
			padding: 0.5em 1em;
			font-size: 1rem;
			border: 0px;
			background-color: #6F42C2 !important;
			color: white;
			border-radius: 4px;
			margin: 0px;
			margin-top: 5px;
			transition: 0.2s;
			width: 100%;
			font-weight: bold;
			font-family: 'Lato', sans-serif;
		}

		.appointment-page .appointment_container{
			background: white;
			padding: 20px 20px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
			border-radius: 10px;
			width: 100%;
			text-align: center;
		}

		.appointment-page #appointmentForm{
			width: 100% !important;
		}

		.modal-actions{
			display: flex;
			justify-content: space-between;
		}
		.modal-actions button{
			padding: 0.5em 1em;
			font-size: 1rem;
			border: 0px;
			background-color: #6F42C2;
			color: white;
			border-radius: 4px;
			margin: 0px;
			margin-top: 5px;
			transition: 0.2s;
			width: 48%;
			font-weight: bold;
			font-family: 'Lato', sans-serif;
		}

		.modal-actions button#closeModal{
			background-color: #adacb3 !important;
		}
		
		#cancelModal .modal-box h3{
			padding-bottom: 10px;
		}

		.appointment_container #appointmentDescriptionInput {
			height: 60px !important;
		}

	</style>

	<nav class="navbar navbar-expand-lg navbar-light">
		<div class="container">
			<a class="navbar-brand" href="index.php">Dentist <b>ME</b></a>
			<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav ml-auto">

					<?php
					if (!isset($_SESSION['patient_ID']) && !isset($_SESSION['doctor_ID'])) {
						// Guest view
					?>
						<li class="nav-item">
							<a class="nav-link" href="appointment.php">Appointment</a>
						</li>
						<li class="nav-item">
							<a class="nav-link" href="booking.php">Book now</a>
						</li>
						<li class="nav-item dropdown nav-dropdown">
							<a class="nav-link dropdown-toggle dropdown-link" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								Log In/Sign Up
							</a>
							<div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
								<a class="dropdown-item" href="patient-login.php">Patient</a>
								<a class="dropdown-item" href="doctor-login.php">Doctor</a>
							</div>
						</li>
					<?php
					} elseif (isset($_SESSION['patient_ID'])) {
						// Patient view
					?>
						<li class="nav-item">
							<a class="nav-link" href="appointment.php">Appointment</a>
						</li>
						<li class="nav-item">
							<a class="nav-link" href="booking.php">Book now</a>
						</li>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="loggedInDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="display: flex; align-items: center; gap: 8px;">
								<?= htmlspecialchars($_SESSION['patient_name']) ?>
								<img src="./assets/img/user_icon.png" width="30px" alt="User Icon" style="opacity: 0.6;">
							</a>
							<div class="dropdown-menu" aria-labelledby="loggedInDropdown">
								<a class="dropdown-item" href="patient-logout.php">Logout</a>
							</div>
						</li>
					<?php
					} elseif (isset($_SESSION['doctor_ID'])) {
						// Doctor view
					?>
						<li class="nav-item">
							<a class="nav-link" href="schedule.php">View Schedule</a>
						</li>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="loggedInDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="display: flex; align-items: center; gap: 8px;">
								Dr. <?= htmlspecialchars($_SESSION['doctor_name']) ?>
								<img src="./assets/img/user_icon.png" width="30px" alt="Doctor Icon" style="opacity: 0.6;">
							</a>
							<div class="dropdown-menu" aria-labelledby="loggedInDropdown">
								<a class="dropdown-item" href="doctor-logout.php">Logout</a>
							</div>
						</li>
					<?php
					}
					?>

				</ul>
			</div>
		</div>
	</nav>