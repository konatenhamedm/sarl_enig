<?php

namespace App\Controller;

use App\Repository\TableInfoTrait;
use App\Service\Services;
use App\Entity\Marque;
use App\Service\FormError;
use App\Form\MarqueType;
use App\Service\ActionRender;
use App\Repository\MarqueRepository;
use App\Service\UploaderHelper;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Omines\Adapter\ArrayAdapter;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\HttpFoundation\Request;
use Omines\DataTablesBundle\Column\TextColumn;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/admin")
 * il s'agit du marque des module
 */
class MarqueController extends AbstractController
{
    use TableInfoTrait;
    /**
     * @Route("/marque/{id}/confirmation", name="marque_confirmation", methods={"GET"})
     * @param $id
     * @param Marque $parent
     * @return Response
     */
    public function confirmation($id,Marque $parent): Response
    {
        return $this->render('_admin/modal/confirmation.html.twig',[
            'id'=>$id,
            'action'=>'marque',
        ]);
    }

    /**
     * @Route("/marque", name="marque")
     * @param Request $request
     * @param DataTableFactory $dataTableFactory
     * @param MarqueRepository $marqueRepository
     * @return Response
     */
    public function index(Request $request,
                          DataTableFactory $dataTableFactory,
                          MarqueRepository $marqueRepository): Response
    {

        $table = $dataTableFactory->create();

        $user = $this->getUser();

        $requestData = $request->request->all();

        $offset = intval($requestData['start'] ?? 0);
        $limit = intval($requestData['length'] ?? 10);

        $searchValue = $requestData['search']['value'] ?? null;


        $totalData = $marqueRepository->countAll();
        $totalFilteredData = $marqueRepository->countAll($searchValue);
        $data = $marqueRepository->getAll($limit, $offset,  $searchValue);

//dd($data);


        $table->createAdapter(ArrayAdapter::class, [
            'data' => $data,
            'totalRows' => $totalData,
            'totalFilteredRows' => $totalFilteredData
        ]) ->setName('dt_');
        ;


        $table->add('libelle', TextColumn::class, ['label' => 'Libelle', 'className' => 'w-100px']);

        $renders = [
            'edit' =>  new ActionRender(function () {
                return true;
            }),
            /*    'suivi' =>  new ActionRender(function () use ($etat) {
                    return in_array($etat, ['cree']);
                }),*/
            'delete' => new ActionRender(function (){
                return true;
            }),
            'details' => new ActionRender(function () {
                return true;
            }),
        ];


        $hasActions = false;

        foreach ($renders as $_ => $cb) {
            if ($cb->execute()) {
                $hasActions = true;
                break;
            }
        }


        if ($hasActions) {
            $table->add('id', TextColumn::class, [
                'label' => 'Actions'
                , 'field' => 'id'
                , 'orderable' => false
                ,'globalSearchable' => false
                ,'className' => 'grid_row_actions'
                , 'render' => function ($value, $context) use ($renders) {

                    $options = [
                        'default_class' => 'btn btn-xs btn-clean btn-icon mr-2 ',
                        'target' => '#extralargemodal1',

                        'actions' => [
                            'edit' => [
                                'url' => $this->generateUrl('marque_edit', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-edit'
                                , 'attrs' => ['class' => 'btn-success']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['edit'];
                                })
                            ],
                            'details' => [
                                'url' => $this->generateUrl('marque_show', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-eye'
                                , 'attrs' => ['class' => 'btn-primary']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['details'];
                                })
                            ],
                            /*   'delete' => [
                                   'url' => $this->generateUrl('marque_delete', ['id' => $value])
                                   , 'ajax' => true
                                   , 'icon' => '%icon% fe fe-trash-2'
                                   , 'attrs' => ['class' => 'btn-danger', 'title' => 'Suppression']
                                   , 'target' => '#smallmodal'
                                   ,  'render' => new ActionRender(function () use ($renders) {
                                       return $renders['delete'];
                                   })
                               ],*/
                        ]
                    ];
                    return $this->renderView('_includes/default_actions.html.twig', compact('options', 'context'));
                }
            ]);
        }


        $table->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('_admin/marque/index.html.twig', ['datatable' => $table, 'titre' => 'Liste des marques']);
    }
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security->getUser()->getUserIdentifier();

    }

    /**
     * @Route("/marque/new", name="marque_new", methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function new(Request $request,UploaderHelper $uploaderHelper, FormError $formError, EntityManagerInterface  $em): Response
    {


        $marque = new Marque();
        $form = $this->createForm(marqueType::class,$marque, [
            'method' => 'POST',
            'action' => $this->generateUrl('marque_new')
        ]);

        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if($form->isSubmitted())
        {

            $statut = 1;
            $response = [];
            $redirect = $this->generateUrl('marque');
            $uploadedFile = $form['image']->getData();
            //dd($format);
            if ($form->isValid()) {


//dd($uploadedFile);
                if ($uploadedFile) {
                    $newFilename = $uploaderHelper->uploadImage($uploadedFile);
                    $marque->setLogo($newFilename);
                }
                $marque->setActive(1);
                $em->persist($marque);
                $em->flush();

                $data = true;
                $message       = 'Opération effectuée avec succès';
                $this->addFlash('success', $message);
            } else {
                $message = $formError->all($form);
                $statut = 0;
                if (!$isAjax) {
                    $this->addFlash('warning', $message);
                }
            }


            /*  }*/
            if ($isAjax) {
                return $this->json( compact('statut', 'message', 'redirect', 'data'));
            } else {
                if ($statut == 1) {
                    return $this->redirect($redirect);
                }
            }

            if ($isAjax) {
                return $this->json(compact('statut', 'message', 'redirect', 'data'));
            } else {
                if ($statut == 1) {
                    return $this->redirect($redirect);
                }
            }
        }

        return $this->render('_admin/marque/new.html.twig', [
            'marque' => $marque,
            'form' => $form->createView(),
            'titre' => 'Marque',
        ]);
    }

    /**
     * @Route("/marque/{id}/edit", name="marque_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Marque $marque
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function edit(Request $request,UploaderHelper $uploaderHelper, FormError $formError, Marque $marque, EntityManagerInterface  $em): Response
    {

        $form = $this->createForm(MarqueType::class,$marque, [
            'method' => 'POST',
            'action' => $this->generateUrl('marque_edit',[
                'id'=>$marque->getId(),
            ])
        ]);
        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if($form->isSubmitted())
        {
            $statut = 1;
            $response = [];
            $redirect = $this->generateUrl('marque');
            $uploadedFile = $form['image']->getData();
            if($form->isValid()){
                if ($uploadedFile) {
                    $newFilename = $uploaderHelper->uploadImage($uploadedFile);
                    $marque->setLogo($newFilename);
                }

                $em->persist($marque);
                $em->flush();

                $message       = 'Opération effectuée avec succès';
                $data = true;
                $this->addFlash('success', $message);

            } else {
                $message = $formError->all($form);
                $statut = 0;
                if (!$isAjax) {
                    $this->addFlash('warning', $message);
                }
            }

            if ($isAjax) {
                return $this->json( compact('statut', 'message', 'redirect', 'data'));
            } else {
                if ($statut == 1) {
                    return $this->redirect($redirect);
                }
            }
        }

        return $this->render('_admin/marque/edit.html.twig', [
            'marque' => $marque,
            'form' => $form->createView(),
            'titre' => 'Marque',
        ]);
    }

    /**
     * @Route("/marque/{id}/show", name="marque_show", methods={"GET"})
     * @param marque $marque
     * @return Response
     */
    public function show(marque $marque): Response
    {
        $form = $this->createForm(MarqueType::class,$marque, [
            'method' => 'POST',
            'action' => $this->generateUrl('marque_show',[
                'id'=>$marque->getId(),
            ])
        ]);

        return $this->render('_admin/marque/voir.html.twig', [
            'marque' => $marque,
            'titre' => 'Marque',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/marque/{id}/active", name="marque_active", methods={"GET"})
     * @param $id
     * @param Marque $marque
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function active($id,Marque $marque, EntityManagerInterface $entityManager): Response
    {

        if ($marque->getActive() == 1){

            $marque->setActive(0);

        }else{

            $marque->setActive(1);

        }
        $entityManager->persist($marque);
        $entityManager->flush();
        return $this->json([
            'code'=>200,
            'message'=>'ça marche bien',
            'active'=>$marque->getActive(),
        ],200);


    }


    /**
     * @Route("/marque/{id}/delete", name="marque_delete", methods={"POST","GET","DELETE"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param Marque $marque
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $em,Marque $marque): Response
    {


        $form = $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'marque_delete'
                    ,   [
                        'id' => $marque->getId()
                    ]
                )
            )
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->remove($marque);
            $em->flush();

            $redirect = $this->generateUrl('marque');

            $message = 'Opération effectuée avec succès';

            $response = [
                'statut'   => 1,
                'message'  => $message,
                'redirect' => $redirect,
                'data' => true
            ];

            $this->addFlash('success', $message);

            if (!$request->isXmlHttpRequest()) {
                return $this->redirect($redirect);
            } else {
                return $this->json($response);
            }



        }
        return $this->render('_admin/marque/delete.html.twig', [
            'marque' => $marque,
            'form' => $form->createView(),
        ]);
    }

}
