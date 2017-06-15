    <html>
    <head>
        <title>Daytona <?php echo isset($pageTitle) ? " - $pageTitle" : ""; ?></title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/style.css"> <!-- Resource style -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
	<link rel="stylesheet" href="css/secondary.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
	<script src="js/jquery.menu-aim.js"></script>
	<script src="js/navigation-bar.js"></script>
        <script src="js/daytona.js"></script>
    </head>

    <body>

    <header class="cd-main-header">
	<a href="/"><img src="../images/daytona_white_text.png" style="margin-right:10px;vertical-align:top;padding-top:5px" width="250px" height="65px"></img></a>
        <a href="#0" class="cd-nav-trigger"><span></span></a>
        <nav class="cd-nav">
          <ul class="cd-top-nav" id="top-side-nav">
          </ul>
        </nav>
    </header>

       <main class="cd-main-content">
        <nav class="cd-side-nav">
            <ul id="navigation-panel">
            </ul>
        </nav>
        <i class="fa fa-arrow-left side-nav-collapse" onClick="collapseSidePanel(this)"></i>

        <div class="content-wrapper" id="top-nav-bar"></div>


