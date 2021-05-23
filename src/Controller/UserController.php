<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;
use App\Services\JwtAuth;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends AbstractController
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
            'path' => 'src/Controller/UserController.php',
        ]);
    }

    public function create (Request $request) {

        $json = $request->get('json', null);

        $params = json_decode($json);
        
        $data = [
            'status' => 'error',
            'code' =>  200,
            'message' => 'Usuario no se ha creado.',
            'json' => $params
        ];

        if($json != null) {
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)) {
               $user = New User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \DateTime('now'));

                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);
                
                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();

                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));

                if(count($isset_user) == 0) {

                    $em->persist($user);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' =>  200,
                        'message' => 'Usuario creado correctamente.',
                        'user' => $user
                    ];
                }else{
                    $data = [
                        'status' => 'error',
                        'code' =>  400,
                        'message' => 'El usuario ya existe.'
                    ];
                }

            }else{
                $data = [
                    'status' => 'success',
                    'code' =>  200,
                    'message' => 'Validación incorrecta'
                ];
            }

        }

        return $this->resjson($data);

    }

    public function login(Request $request, JwtAuth $jwtAuth) {

        $json = $request->get('json', null);
        $params = json_decode($json);

        $data = [
            'status' => 'error',
            'code' =>  200,
            'message' => 'El usuario no se ha podido identificar.'
        ];

        if($json != null) {
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && !empty($password) && count($validate_email) == 0) {
                $pwd = hash('sha256', $password);
                
                if($gettoken) {
                    $signup = $jwtAuth->signup($email, $pwd, $gettoken);
                }else{
                    $signup = $jwtAuth->signup($email, $pwd);
                }

                return new JsonResponse($signup);
            }
        }

        return $this->resjson($data);
    }

    public function edit(Request $request, JwtAuth $jwtAuth){

        $token = $request->headers->get('Authorization');

        $checkToken = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Usuario no actualizado.'
        ];

        if($checkToken) {
            $em = $this->getDoctrine()->getManager();
            $identity = $jwtAuth->checkToken($token, true);
            
            $user_repo = $this->getDoctrine()->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id' => $identity->sub
            ]);

            $json = $request->get('json', null);
            $params = json_decode($json);

            if(!empty($json)) {
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;

                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);

                if(!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)) {
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setSurname($surname);

                    $isset_user = $user_repo->findBy([
                        'email' => $email
                    ]);

                    if(count($isset_user) == 0 || $identity->email == $email){
                        $em->persist($user);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Usuario actualizado correctamente.',
                            'user' => $user
                        ];
                    }else{
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'No puedes usar ese email.'
                        ];
                    }
                }
            } 
        }

        return $this->resjson($data);
    }

}