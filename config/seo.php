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
        // Byråernas sidor är kopior av För företagare med egen profil och ska aldrig indexeras.
        'byra',
        'byra/*',
        // Tillfälligt dolt tills För företagare är klart att lanseras.
        'for-foretagare',
        'for-foretagare/*',
    ],

];
