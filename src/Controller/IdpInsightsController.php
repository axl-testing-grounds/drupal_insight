<?php

namespace Drupal\idp_insights\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\idp_insights\IdpInsightService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for idp_insights routes.
 */
class IdpInsightsController extends ControllerBase {


  /**
   * IDP Insight Service.
   *
   * @var \Drupal\idp_insights\IdpInsightService
   */
  protected $idpService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('idp_insights.service'),
    );
  }

  /**
   * Constructs a SystemInfoController object.
   *
   * @param \Drupal\idp_insights\IdpInsightService $idp_service
   *   IDP Insight service.
   */
  public function __construct(IdpInsightService $idp_service) {
    $this->idpService = $idp_service;
  }

  /**
   * Builds the response.
   */
  public function buildData($req_name, Request $request) {
    $idpToken = $request->headers->get('idp-token');
    $methodName = 'get' . $req_name;
    $statusCode = 500;
    if ($this->idpService->checkIfValidRequest($idpToken) && method_exists($this->idpService, $methodName)) {
      $result = call_user_func([$this->idpService, $methodName]);
      $statusCode = 200;
    }
    else {
      $result = [
        'error' => 'Invalid request',
      ];
    }

    return new JsonResponse($result, $statusCode);
  }

}
