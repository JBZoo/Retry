<?php
namespace STS\Backoff;

use PHPUnit\Framework\TestCase;
use STS\Backoff\Strategies\ConstantStrategy;
use STS\Backoff\Strategies\ExponentialStrategy;
use STS\Backoff\Strategies\LinearStrategy;
use STS\Backoff\Strategies\PolynomialStrategy;

class BackoffTest extends TestCase
{
    public function testDefaults()
    {
        $b = new Backoff();

        $this->assertEquals(5, $b->getMaxAttempts());
        $this->assertInstanceOf(PolynomialStrategy::class, $b->getStrategy());
        $this->assertFalse($b->jitterEnabled());
    }

    public function testConstructorParams()
    {
        $b = new Backoff(10, "linear");

        $this->assertEquals(10, $b->getMaxAttempts());
        $this->assertInstanceOf(LinearStrategy::class, $b->getStrategy());
    }

    public function testStrategyKeys()
    {
        $b = new Backoff();

        $b->setStrategy("constant");
        $this->assertInstanceOf(ConstantStrategy::class, $b->getStrategy());

        $b->setStrategy("linear");
        $this->assertInstanceOf(LinearStrategy::class, $b->getStrategy());

        $b->setStrategy("polynomial");
        $this->assertInstanceOf(PolynomialStrategy::class, $b->getStrategy());

        $b->setStrategy("exponential");
        $this->assertInstanceOf(ExponentialStrategy::class, $b->getStrategy());
    }

    public function testStrategyInstances()
    {
        $b = new Backoff();

        $b->setStrategy(new ConstantStrategy());
        $this->assertInstanceOf(ConstantStrategy::class, $b->getStrategy());

        $b->setStrategy(new LinearStrategy());
        $this->assertInstanceOf(LinearStrategy::class, $b->getStrategy());

        $b->setStrategy(new PolynomialStrategy());
        $this->assertInstanceOf(PolynomialStrategy::class, $b->getStrategy());

        $b->setStrategy(new ExponentialStrategy());
        $this->assertInstanceOf(ExponentialStrategy::class, $b->getStrategy());
    }

    public function testClosureStrategy()
    {
        $b = new Backoff();

        $strategy = function() {
            return "hi there";
        };

        $b->setStrategy($strategy);

        $this->assertEquals("hi there", call_user_func($b->getStrategy()));
    }

    public function testIntegerReturnsConstantStrategy()
    {
        $b = new Backoff();

        $b->setStrategy(500);

        $this->assertInstanceOf(ConstantStrategy::class, $b->getStrategy());
    }

    public function testInvalidStrategy()
    {
        $b = new Backoff();

        $this->expectException(\InvalidArgumentException::class);
        $b->setStrategy("foo");
    }

    public function testWaitTimes()
    {
        $b = new Backoff(1, "linear");

        $this->assertEquals(100, $b->getStrategy()->getBase());

        $this->assertEquals(100, $b->getWaitTime(1));
        $this->assertEquals(200, $b->getWaitTime(2));
    }

    public function testWaitCap()
    {
        $b = new Backoff(1, new LinearStrategy(5000));

        $this->assertEquals(10000, $b->getWaitTime(2));

        $b->setWaitCap(5000);

        $this->assertEquals(5000, $b->getWaitTime(2));
    }

    public function testWait()
    {
        $b = new Backoff(1, new LinearStrategy(50));

        $start = microtime(true);

        $b->wait(2);

        $end = microtime(true);

        $elapsedMS =  ($end - $start) * 1000;

        // We expect that this took just barely over the 100ms we asked for
        $this->assertTrue($elapsedMS > 100 && $elapsedMS < 110);
    }

    public function testSuccessfulWork()
    {
        $b = new Backoff();

        $result = $b->run(function() {
            return "done";
        });

        $this->assertEquals("done", $result);
    }

    public function testFirstAttemptDoesNotCallStrategy()
    {
        $b = new Backoff();
        $b->setStrategy(function() {
            throw new \Exception("We shouldn't be here");
        });

        $result = $b->run(function() {
            return "done";
        });

        $this->assertEquals("done", $result);
    }

    public function testFailedWorkReThrowsException()
    {
        $b = new Backoff(2, new ConstantStrategy(0));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("failure");

        $b->run(function() {
            throw new \Exception("failure");
        });
    }

    public function testAttempts()
    {
        $b = new Backoff(10, new ConstantStrategy(0));

        $attempt = 0;

        $result = $b->run(function() use(&$attempt) {
            $attempt++;

            if($attempt < 5) {
                throw new \Exception("failure");
            }

            return "success";
        });

        $this->assertEquals(5, $attempt);
        $this->assertEquals("success", $result);
    }

    public function testJitter()
    {
        $b = new Backoff(10, new ConstantStrategy(1000));

        // First without jitter
        $this->assertEquals(1000, $b->getWaitTime(1));

        // Now with jitter
        $b->enableJitter();

        // Because it's still possible that I could get 1000 back even with jitter, I'm going to generate two
        $waitTime1 = $b->getWaitTime(1);
        $waitTime2 = $b->getWaitTime(1);

        // And I'm banking that I didn't hit the _extremely_ rare chance that both were randomly chosen to be 1000 still
        $this->assertTrue($waitTime1 < 1000 || $waitTime2 < 1000);
    }
}