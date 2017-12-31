<?php

namespace Amp;

final class Producer implements Iterator {
    /** @var \Amp\Internal\Producer */
    private $emitter;

    /** @var \Amp\Iterator */
    private $iterator;

    /**
     * @param callable(callable(mixed $value): Promise $emit): \Generator $producer
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $producer) {
        $this->emitter = $emitter = new class {
            use Internal\Producer {
                emit as public;
                complete as public;
                fail as public;
            }
        };

        if (\PHP_VERSION_ID < 70100) {
            $emit = static function ($value) use ($emitter): Promise {
                return $emitter->emit($value);
            };
        } else {
            $emit = \Closure::fromCallable([$this->emitter, "emit"]);
        }

        $result = $producer($emit);

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }

        $coroutine = new Coroutine($result);
        $coroutine->onResolve(static function ($exception) use ($emitter) {
            if ($exception) {
                $emitter->fail($exception);
                return;
            }

            $emitter->complete();
        });

        $this->iterator = $this->emitter->iterate();
    }

    /**
     * {@inheritdoc}
     */
    public function advance(): Promise {
        return $this->iterator->advance();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrent() {
        return $this->iterator->getCurrent();
    }
}
