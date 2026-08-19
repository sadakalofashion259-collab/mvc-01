<?php
declare(strict_types=1);

interface CaptchaServiceInterface {
    public function verify(): bool;
    public function getErrorMessage(): string;
    public function isPostRequest(): bool;
}
