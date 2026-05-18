<?php
$jsUrl = "https://cdn.jsdelivr.net/npm/flatpickr";
$cssUrl = "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css";

$js = file_get_contents($jsUrl);
$css = file_get_contents($cssUrl);

if ($js) file_put_contents(__DIR__ . '/assets/flatpickr.js', $js);
if ($css) file_put_contents(__DIR__ . '/assets/flatpickr.css', $css);

echo "Downloaded Flatpickr.";
?>
