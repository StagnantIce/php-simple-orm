<?php

class Product extends Record {
    public ?int $id = null;
    public float $price;
    public ?string $name = null;
    public ?string $text = null;
}
