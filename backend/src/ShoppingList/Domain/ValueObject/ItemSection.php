<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

/**
 * A store section a member chose for a list item (PD-92). The ids are those of the Catalog's
 * sections (PD-90), kept apart so this context never imports Catalog's domain.
 */
enum ItemSection: string
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
