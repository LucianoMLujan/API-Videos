<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;
use App\Services\JwtAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;

class VideoController extends AbstractController
{

    private function resjson($data) {
        //Serializar datos con servicios serializer
        $json = $this->get('serializer')->serialize($data, 'json');

        //Response con http foundation
        $response = new Response();

        //Asignar contenido a la respuesta
        $response->setContent($json);

        //Inidicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        //Devolver la respuesta

        return $response;
    }

    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwtAuth, $id = null) {

        $token = $request->headers->get('Authorization', null);
        $checkToken = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El video no ha podido crearse.'
        ];

        if($checkToken) {
            $json = $request->get('json', null);
            $params = json_decode($json);

            $identity = $jwtAuth->checkToken($token, true);

            if(!empty($json)) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $url = (!empty($params->url)) ? $params->url : null;

                if(!empty($user_id) && !empty($title)) {
                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                        'id' => $user_id
                    ]);
                    
                    //Guardo o actualizo el video según el parametro id
                    if($id == null) {
                        $video = new Video();
                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');
                        $createdAt = new \DateTime('now');
                        $updatedAt = new \DateTime('now');
                        $video->setCreatedAt($createdAt);
                        $video->setUpdatedAt($updatedAt);

                        $em->persist($video);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El video se ha guardado correctamente.',
                            'video' => $video
                        ];
                    }else{
                        $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            'id' => $id,
                            'user' => $identity->sub
                        ]);

                        if($video && is_object($video)) {
                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
                            $updatedAt = new \DateTime('now');
                            $video->setUpdatedAt($updatedAt);

                            $em->persist($video);
                            $em->flush();

                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'El video se actualizado correctamente.',
                                'video' => $video
                            ];
                        }

                    }
                }
            }
        }

        return $this->resjson($data);
    }

    public function videos(Request $request, JwtAuth $jwtAuth, PaginatorInterface $paginatorInterface) {

        $token = $request->headers->get('Authorization');
        $checkToken = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => '404',
            'message' => 'No se pueden listar los videos en este momento.'
        ];

        if($checkToken) {
            $identity = $jwtAuth->checkToken($token, true);
            $em = $this->getDoctrine()->getManager();

            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC";
            $query = $em->createQuery($dql);

            $page = $request->query->getInt('page', 1);
            $itemsPerPage = 5;
            $paginatorInterface = $paginatorInterface->paginate($query, $page, $itemsPerPage);
            $total = $paginatorInterface->getTotalItemCount();

            $data = [
                'status' => 'success',
                'code' => '200',
                'total_items_count' => $total,
                'actual_page' => $page,
                'items_per_page' => $itemsPerPage,
                'total_pages' => ceil($total / $itemsPerPage),
                'videos' => $paginatorInterface,
                'user_id' => $identity->sub
            ];

        }

        return $this->resjson($data);
    }

    public function video(Request $request, JwtAuth $jwtAuth, $id = null) {

        $token = $request->headers->get('Authorization');
        $checkToken = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => '404',
            'message' => 'Video no encontrado.'
        ];

        if($checkToken) {
            $identity = $jwtAuth->checkToken($token, true);
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id,
                'user' => $identity->sub
            ]);

            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'video' => $video
                ];
            }
        }

        return $this->resjson($data);
    }

    public function remove(Request $request, JwtAuth $jwtAuth, $id = null) {

        $token = $request->headers->get('Authorization');
        $checkToken = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => '404',
            'message' => 'Video no encontrado.'
        ];

        if($checkToken) {
            $identity = $jwtAuth->checkToken($token, true);

            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            $video = $doctrine->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);

            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $em->remove($video);
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'message' => 'Video eliminado correctamente.',
                    'video' => $video
                ];
            }

        }

        return $this->resjson($data);

    }

}
