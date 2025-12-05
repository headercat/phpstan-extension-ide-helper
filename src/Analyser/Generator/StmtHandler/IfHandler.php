<?php 

namespace PHPStan\Analyser\Generator\StmtHandler;
return;

use Generator;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\If_;
use PHPStan\Analyser\ExpressionContext;
use PHPStan\Analyser\Generator\ExprAnalysisRequest;
use PHPStan\Analyser\Generator\GeneratorScope;
use PHPStan\Analyser\Generator\InternalEndStatementResult;
use PHPStan\Analyser\Generator\NodeCallbackRequest;
use PHPStan\Analyser\Generator\StmtAnalysisResult;
use PHPStan\Analyser\Generator\StmtHandler;
use PHPStan\Analyser\Generator\StmtsAnalysisRequest;
use PHPStan\Analyser\StatementContext;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use function array_merge;
use function count;

/**
 * @implements StmtHandler<If_>
 */
#[AutowiredService]
final class IfHandler implements StmtHandler
{

	public function __construct(
		#[AutowiredParameter]
		private readonly bool $treatPhpDocTypesAsCertain,
	)
	{
	}

	public function supports(Stmt $stmt): bool
	{
		return $stmt instanceof If_;
	}

	public function analyseStmt(Stmt $stmt, GeneratorScope $scope, StatementContext $context, ?callable $alternativeNodeCallback): Generator
	{
		$condResult = yield new ExprAnalysisRequest($stmt, $stmt->cond, $scope, ExpressionContext::createDeep(), $alternativeNodeCallback);
		$conditionType = ($this->treatPhpDocTypesAsCertain ? $condResult->type : $condResult->nativeType)->toBoolean();
		$ifAlwaysTrue = $conditionType->isTrue()->yes();
		$exitPoints = [];
		$throwPoints = $condResult->throwPoints;
		$impurePoints = $condResult->impurePoints;
		$endStatements = [];
		$finalScope = null;
		$alwaysTerminating = true;
		$hasYield = $condResult->hasYield;

		$truthyScopeGen = $condResult->scope->applySpecifiedTypes($condResult->specifiedTruthyTypes);
		yield from $truthyScopeGen;
		$branchScopeStatementResult = yield new StmtsAnalysisRequest($stmt, $stmt->stmts, $truthyScopeGen->getReturn(), $context, $alternativeNodeCallback);

		if (!$conditionType->isTrue()->no()) {
			$exitPoints = $branchScopeStatementResult->exitPoints;
			$throwPoints = array_merge($throwPoints, $branchScopeStatementResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $branchScopeStatementResult->impurePoints);
			$branchScope = $branchScopeStatementResult->scope;
			$finalScope = $branchScopeStatementResult->isAlwaysTerminating ? null : $branchScope;
			$alwaysTerminating = $branchScopeStatementResult->isAlwaysTerminating;
			if (count($branchScopeStatementResult->endStatements) > 0) {
				$endStatements = array_merge($endStatements, $branchScopeStatementResult->endStatements);
			} elseif (count($stmt->stmts) > 0) {
				$endStatements[] = new InternalEndStatementResult($stmt->stmts[count($stmt->stmts) - 1], $branchScopeStatementResult);
			} else {
				$endStatements[] = new InternalEndStatementResult($stmt, $branchScopeStatementResult);
			}
			$hasYield = $branchScopeStatementResult->hasYield || $hasYield;
		}

		$falseyScopeGen = $condResult->scope->applySpecifiedTypes($condResult->specifiedFalseyTypes);
		yield from $falseyScopeGen;

		$scope = $falseyScopeGen->getReturn();
		$lastElseIfConditionIsTrue = false;

		$condScope = $scope;
		foreach ($stmt->elseifs as $elseif) {
			yield new NodeCallbackRequest($elseif, $scope, $alternativeNodeCallback);
			$condResult = yield new ExprAnalysisRequest($stmt, $elseif->cond, $condScope, ExpressionContext::createDeep(), $alternativeNodeCallback);
			$elseIfConditionType = ($this->treatPhpDocTypesAsCertain ? $condResult->type : $condResult->nativeType)->toBoolean();
			$throwPoints = array_merge($throwPoints, $condResult->throwPoints);
			$impurePoints = array_merge($impurePoints, $condResult->impurePoints);
			$condScope = $condResult->scope;

			$truthyScopeGen = $condScope->applySpecifiedTypes($condResult->specifiedTruthyTypes);
			yield from $truthyScopeGen;
			$branchScopeStatementResult = yield new StmtsAnalysisRequest($elseif, $elseif->stmts, $truthyScopeGen->getReturn(), $context, $alternativeNodeCallback);

			if (
				!$ifAlwaysTrue
				&& !$lastElseIfConditionIsTrue
				&& !$elseIfConditionType->isTrue()->no()
			) {
				$exitPoints = array_merge($exitPoints, $branchScopeStatementResult->exitPoints);
				$throwPoints = array_merge($throwPoints, $branchScopeStatementResult->throwPoints);
				$impurePoints = array_merge($impurePoints, $branchScopeStatementResult->impurePoints);
				$branchScope = $branchScopeStatementResult->scope;
				$finalScope = $branchScopeStatementResult->isAlwaysTerminating ? $finalScope : $branchScope->mergeWith($finalScope);
				$alwaysTerminating = $alwaysTerminating && $branchScopeStatementResult->isAlwaysTerminating;
				if (count($branchScopeStatementResult->endStatements) > 0) {
					$endStatements = array_merge($endStatements, $branchScopeStatementResult->endStatements);
				} elseif (count($elseif->stmts) > 0) {
					$endStatements[] = new InternalEndStatementResult($elseif->stmts[count($elseif->stmts) - 1], $branchScopeStatementResult);
				} else {
					$endStatements[] = new InternalEndStatementResult($elseif, $branchScopeStatementResult);
				}
				$hasYield = $hasYield || $branchScopeStatementResult->hasYield;
			}

			if ($elseIfConditionType->isTrue()->yes()) {
				$lastElseIfConditionIsTrue = true;
			}

			$falseyScopeGen = $condScope->applySpecifiedTypes($condResult->specifiedFalseyTypes);
			yield from $falseyScopeGen;

			$condScope = $falseyScopeGen->getReturn();
			$scope = $condScope;
		}

		if ($stmt->else === null) {
			if (!$ifAlwaysTrue && !$lastElseIfConditionIsTrue) {
				$finalScope = $scope->mergeWith($finalScope);
				$alwaysTerminating = false;
			}
		} else {
			yield new NodeCallbackRequest($stmt->else, $scope, $alternativeNodeCallback);
			$branchScopeStatementResult = yield new StmtsAnalysisRequest($stmt->else, $stmt->else->stmts, $scope, $context, $alternativeNodeCallback);

			if (!$ifAlwaysTrue && !$lastElseIfConditionIsTrue) {
				$exitPoints = array_merge($exitPoints, $branchScopeStatementResult->exitPoints);
				$throwPoints = array_merge($throwPoints, $branchScopeStatementResult->throwPoints);
				$impurePoints = array_merge($impurePoints, $branchScopeStatementResult->impurePoints);
				$branchScope = $branchScopeStatementResult->scope;
				$finalScope = $branchScopeStatementResult->isAlwaysTerminating ? $finalScope : $branchScope->mergeWith($finalScope);
				$alwaysTerminating = $alwaysTerminating && $branchScopeStatementResult->isAlwaysTerminating;
				if (count($branchScopeStatementResult->endStatements) > 0) {
					$endStatements = array_merge($endStatements, $branchScopeStatementResult->endStatements);
				} elseif (count($stmt->else->stmts) > 0) {
					$endStatements[] = new InternalEndStatementResult($stmt->else->stmts[count($stmt->else->stmts) - 1], $branchScopeStatementResult);
				} else {
					$endStatements[] = new InternalEndStatementResult($stmt->else, $branchScopeStatementResult);
				}
				$hasYield = $hasYield || $branchScopeStatementResult->hasYield;
			}
		}

		if ($finalScope === null) {
			$finalScope = $scope;
		}

		if ($stmt->else === null && !$ifAlwaysTrue && !$lastElseIfConditionIsTrue) {
			$endStatements[] = new InternalEndStatementResult($stmt, new StmtAnalysisResult($finalScope, $hasYield, $alwaysTerminating, $exitPoints, $throwPoints, $impurePoints));
		}

		return new StmtAnalysisResult($finalScope, $hasYield, $alwaysTerminating, $exitPoints, $throwPoints, $impurePoints, $endStatements);
	}

}
