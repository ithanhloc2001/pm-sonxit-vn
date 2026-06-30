<?php
$files = [
    'c:/xampp/htdocs/style.css',
    'c:/xampp/htdocs/assets/pm/footer.css',
    'c:/xampp/htdocs/assets/pm/chat-widget.css'
];

foreach ($files as $f) {
    if (file_exists($f)) {
        $content = file_get_contents($f);
        if (preg_match_all('/\.btn-link[^{]*\{[^}]*\}/i', $content, $matches)) {
            echo "Matches in $f for .btn-link:\n";
            print_r($matches[0]);
        }
        if (preg_match_all('/\.btn[^{]*\{[^}]*\}/i', $content, $matches)) {
            echo "Matches in $f for .btn:\n";
            print_r($matches[0]);
        }
    }
}
