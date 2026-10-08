<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BusinessRuleException;

trait InteractsWithUi
{
    protected function notify(string $message, string $type = 'success'): void
    {
        $this->dispatch('notify', message: $message, type: $type);
    }

    /**
     * Ejecuta una acción de negocio y muestra el error como notificación si se viola una regla.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T|null
     */
    protected function attempt(callable $action, ?string $successMessage = null): mixed
    {
        try {
            $result = $action();
        } catch (BusinessRuleException $e) {
            $this->notify($e->getMessage(), 'error');

            return null;
        }

        if ($successMessage) {
            $this->notify($successMessage);
        }

        return $result ?? true;
    }
}
