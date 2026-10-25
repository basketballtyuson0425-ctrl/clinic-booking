<?php

// 画面は Next.js（frontend/）が表示し、Laravel は API（routes/api.php）だけを受け持つ。
// そのため、ここにはページを置かない（/ を開くと 404 になる）。
// Laravel が動いているかは /up（bootstrap/app.php の health）で確認できる。
