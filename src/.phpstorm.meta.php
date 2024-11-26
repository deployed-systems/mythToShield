<?php

namespace PHPSTORM_META
{
    //config() - override
    override(\config(), map([
        'MythToShield' => \Deployed\MythToShield\Config\MythToShield::class,
    ]));
}
