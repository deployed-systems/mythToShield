<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
    // identifier: method.childParameterType
    'message' => '#^Parameter \\#1 \\$credentials \\(array\\{email\\?\\: string, username\\?\\: string, password\\?\\: string\\}\\) of method Deployed\\\\MythToShield\\\\Authenticators\\\\MythCompatSession\\:\\:check\\(\\) should be contravariant with parameter \\$credentials \\(array\\) of method CodeIgniter\\\\Shield\\\\Authentication\\\\AuthenticatorInterface\\:\\:check\\(\\)$#',
    'count' => 1,
    'path' => __DIR__ . '/src/Authenticators/MythCompatSession.php',
];
$ignoreErrors[] = [
    // identifier: empty.notAllowed
    'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/src/Authenticators/MythCompatSession.php',
];
return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
