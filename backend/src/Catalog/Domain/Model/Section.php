<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

/**
 * The app's own store sections (PD-90). The cases are in display order, a typical walk through
 * the store, with "other" always last. Their Spanish names live in the frontend (PD-8).
 */
enum Section: string
{
    case FRUIT_AND_VEGETABLES = 'fruit-and-vegetables';
    case BAKERY = 'bakery';
    case MEAT_AND_FISH = 'meat-and-fish';
    case DELI_AND_CHEESE = 'deli-and-cheese';
    case DAIRY_AND_EGGS = 'dairy-and-eggs';
    case PANTRY = 'pantry';
    case FROZEN = 'frozen';
    case DRINKS = 'drinks';
    case HOUSEHOLD = 'household';
    case PERSONAL_CARE = 'personal-care';
    case BABY = 'baby';
    case PETS = 'pets';
    case OTHER = 'other';
}
