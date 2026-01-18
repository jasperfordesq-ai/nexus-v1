<?php

namespace Nexus\Helpers;

class SDG
{
    public static function all()
    {
        return [
            1 => ['label' => 'No Poverty', 'color' => '#E5243B', 'icon' => '🏘️'],
            2 => ['label' => 'Zero Hunger', 'color' => '#DDA63A', 'icon' => '🍲'],
            3 => ['label' => 'Good Health', 'color' => '#4C9F38', 'icon' => '🩺'],
            4 => ['label' => 'Quality Education', 'color' => '#C5192D', 'icon' => '🎓'],
            5 => ['label' => 'Gender Equality', 'color' => '#FF3A21', 'icon' => '⚖️'],
            6 => ['label' => 'Clean Water', 'color' => '#26BDE2', 'icon' => '💧'],
            7 => ['label' => 'Clean Energy', 'color' => '#FCC30B', 'icon' => '⚡'],
            8 => ['label' => 'Decent Work', 'color' => '#A21942', 'icon' => '📈'],
            9 => ['label' => 'Innovation', 'color' => '#FD6925', 'icon' => '🏗️'],
            10 => ['label' => 'Reduced Inequalities', 'color' => '#DD1367', 'icon' => '🤝'],
            11 => ['label' => 'Sustainable Cities', 'color' => '#FD9D24', 'icon' => '🏙️'],
            12 => ['label' => 'Responsible Consumption', 'color' => '#BF8B2E', 'icon' => '♻️'],
            13 => ['label' => 'Climate Action', 'color' => '#3F7E44', 'icon' => '🌍'],
            14 => ['label' => 'Life Below Water', 'color' => '#0A97D9', 'icon' => '🐟'],
            15 => ['label' => 'Life on Land', 'color' => '#56C02B', 'icon' => '🌳'],
            16 => ['label' => 'Peace & Justice', 'color' => '#00689D', 'icon' => '🕊️'],
            17 => ['label' => 'Partnerships', 'color' => '#19486A', 'icon' => '🔗'],
        ];
    }

    public static function get($id)
    {
        $all = self::all();
        return $all[$id] ?? null;
    }
}
