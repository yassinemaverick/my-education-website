<?php
// This file is intentionally a dead end.
// The real admin panel is at a non-guessable URL.
// Any direct access here is either a bot or a probe — respond with 404.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 Not Found</title>
<style>
  body { background:#0f1d2e; color:rgba(255,255,255,0.4); font-family:sans-serif;
         display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  .wrap { text-align:center; }
  h1 { font-size:5rem; font-weight:700; color:rgba(255,255,255,0.08); margin:0; }
  p  { font-size:0.9rem; margin-top:0.5rem; }
  a  { color:rgba(255,255,255,0.25); text-decoration:none; font-size:0.8rem; margin-top:2rem; display:inline-block; }
  a:hover { color:rgba(255,255,255,0.5); }
</style>
</head>
<body>
<div class="wrap">
  <h1>404</h1>
  <p>Page not found.</p>
  <a href="index.php">← Back to main site</a>
</div>
</body>
</html>
