<?php

namespace App\Exceptions;

use DomainException;

/**
 * Violación de una regla de negocio. El mensaje está pensado para mostrarse al usuario.
 */
class BusinessRuleException extends DomainException {}
