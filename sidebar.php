<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-RandomHashHere" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
		background: #f3f4f6;
	}

	input, textarea, select{
		font-family: 'Lato', sans-serif;
		background-color: #F1F0F7;
		border: 0;
		border-radius: 2px;
		padding: 3px 10px;
	}

	input:focus, textarea, select:focus{
		outline: none;
	}

	/* .wrapper {
		background: #fff;
		margin-bottom: 270px;
		box-shadow: 0px 25px 10px -15px rgba(0,0,0,0.08); 
	} */

	select, input[type='date']{
		width: 195px;
	}

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
		background: linear-gradient(to right, #04a3ce 0%, #04a3ce 100%);
		box-shadow: 0px 25px 10px -10px rgba(0, 0, 0, 0.08);
	}

	.nav-dropdown a.dropdown-link {
		color: #f5f5f5 !important;
	}

	.btn-primary {
		color: #fff;
		background: linear-gradient(to right, #04a3ce 0%, #04a3ce 100%) !important;
		border-color: #04a3ce !important;
	}

	.btn-primary:hover {
		color: #fff;
		background-color: #04a3ce !important;
		border-color: #04a3ce !important;
		-webkit-box-shadow: none;
		box-shadow: none;
	}

	.btn-primary:focus {
		box-shadow: 0 0 0 0.2rem #04a3ce !important;
	}
	.sidebar {
		background-color: #2f3039 !important;
		width: 300px;
		position: sticky;  /* make it sticky */
		top: 0;            /* stick to the top when scrolling */
		height: 100vh;     /* full viewport height */
		overflow-y: auto;  /* allow scrolling inside sidebar if content exceeds height */
	}
	.navbar-brand{
		font-size: 35px;
		color: white !important;
		text-align: center;
		margin: 0 !important;
        font-family: 'Poppins', sans-serif;
		padding-bottom: 17px;
	}
	.nav-link{
		font-size: 17px;
	}
	.nav-link i{
		margin-left: 20px;
	}
	.nav-link.active{
		background-color: #04a3ce !important;
		transition: 0.15s;
	}
	.nav-link.active:hover{
		background-color: #1b78aeff !important;
	}
	.nav-pills li, .nav-pills li a{
		width: 100%;
		margin: 0px !important;
		color: white !important;
	}
	.nav-pills li a{
		padding-top: 15px;
		padding-bottom: 15px;
	}
	.nav-pills li a:focus,
	.nav-pills li a:active,
	.nav-pills li a:focus-visible {
		outline: none !important;
		box-shadow: none !important;
		background: none !important; /* optional – prevents active highlight */
	}

	.nav-pills .nav-link.active{
		border-radius: 0px !important;
	}
	.nav-link.active{
		background-color: #04a3ce !important;
		font-weight: bold;
	}
	.nav-link:hover{
		background-color: #454555ff !important;
		color: white;
	}
	table, th, tr, td{
		border: none !important;
		outline: 0 !important;
	}
	thead{
		background-color:rgb(230, 228, 232);
	}
	.table-container{
		background-color: white;
		border-radius: 10px;
		padding: 20px 40px;
		box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
	}
	.table{
		font-weight: bold;
	}
	thead th:first-child {
		border-top-left-radius: 8px; /* adjust radius as needed */
	}

	thead th:last-child {
		border-top-right-radius: 8px; /* adjust radius as needed */
	}
	tbody tr:nth-child(odd) {
    background-color: white;
	}

	tbody tr:nth-child(even) {
		background-color: #f2f2f2; /* light grey */
	}

	button{
		background-color: #04a3ce !important;
		transition: 0.15s;
		border: none;
		border-radius: 8px;
		padding: 8px 19px;
		color: white;
		font-size: 17px;
		font-weight: bold;
	}
	button:hover{
		background-color:#04a3ce !important;
		transform: scale(1.05);
		outline: none;
	}
	.nav-pills li a i {
		padding-right:5px;
	}
	td i{
		padding-right: 10px;
		color: black;
		transition: 0.15s;
	}
	td i:hover{
		padding-right: 10px;
		color: #04a3ce;
		transform: scale(1.2);
	}
	.success-alert{
		background-color: #d5ccff !important;
    	color: #2b0082 !important;
		position: relative;
		padding: .75rem 1.25rem;
		margin-bottom: 1rem;
		border: 1px solid transparent;
		border-radius: .25rem;
		text-align: center;
		padding: 10px 20px;
		margin: 10px 20px;
		border-radius: 5px;
	}
	a:hover{
		text-decoration: none !important;
	}
	input, textarea{
		font-family: 'Lato', sans-serif;
		background-color: #F1F0F7;
		border: 0;
		border-radius: 2px;
		padding: 3px 10px;
	}
	.input-container {
		display: flex
	;
		justify-content: space-between;
		margin-bottom: 5px;
	}
	.details-container{
		background-color: white;
		border-radius: 10px;
		padding: 20px 40px;
		box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
		background: white;
		padding: 20px 20px;
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
		border-radius: 10px;
		width: 100%;
		text-align: center;
	}
	#appointmentForm span{
		font-weight: bold;
	}
	#appointmentForm textarea{
		height: 150px;
	}
	.submit-button{
		padding: 0.5em 1em;
		font-size: 1rem;
		border: 0px;
		background-color: #04a3ce !important;
		color: white;
		border-radius: 4px;
		margin: 0px;
		margin-top: 5px;
		transition: 0.2s;
		width: 100%;
		font-weight: bold;
		font-family: 'Lato', sans-serif;
	}
	.submit-button:hover{
		transform: scale(1.02);
	}
	.back-button{
		color: #212529;
		transition: 0.15s;
		cursor: pointer;
	}
	.back-button:hover{
		color: #04a3ce !important;
		transform: scale(1.02);
	}
</style>
	<nav class="navbar navbar-light bg-light flex-column vh-100 sidebar">
		<div class="flex-column" style="width: 100%; display: flex;">
			<a class="navbar-brand" href="dashboard.php">Sync<b>Share</b></a>
			<ul class="nav nav-pills flex-column mb-auto">
				<li class="nav-item">
					<a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?> active <?php endif; ?>" href="dashboard.php">
						<i class="fas fa-user-shield me-2"></i> Dashboard
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'account-all.php'): ?> active <?php endif; ?>" href="account-all.php">
						<i class="fas fa-users me-2"></i> Accounts
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'post-all.php' || 'post-new.php' || 'post-view.php'): ?> active <?php endif; ?>" href="post-all.php">
						<i class="fas fa-mail-bulk me-2"></i> Posts
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'post-all.php'): ?> active <?php endif; ?>" href="post_all.php">
						<i class="fas fa-user-shield me-2"></i> Posts
					</a>
				</li>
			</ul>
		</div>
	</nav>