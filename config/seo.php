<?php

return [

    /*
    | Sidor som inte ska listas av sökmotorer. Matchar med $request->is(), så
    | "investerartraff" och "investerartraff/*" är giltiga. Listan avslöjas
    | medvetet inte i robots.txt (som är publik).
    */
    'noindex_paths' => [
        'investerartraff',
        'investerartraff/*',
    ],

];
