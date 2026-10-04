<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Fallo al generar la retroalimentacion automatica de un PIREP.
 *
 * Es una excepcion de servicio, no de HTTP: la lanza PirepFeedbackService y la
 * captura el comando phpvms:pireps-ai-feedback. Nunca debe llegar al ciclo de
 * vida del PIREP, que es independiente de esta funcion.
 */
class PirepFeedbackException extends RuntimeException {}
