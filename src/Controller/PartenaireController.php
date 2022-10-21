<?php

namespace App\Controller;

use App\Repository\TableInfoTrait;
use App\Service\Services;
use App\Entity\Partenaire;
use App\Service\FormError;
use App\Form\PartenaireType;
use App\Service\ActionRender;
use App\Repository\PartenaireRepository;
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
 * il s'agit du partenaire des module
 */
class PartenaireController extends AbstractController
{
    use TableInfoTrait;
    /**
     * @Route("/partenaire/{id}/confirmation", name="partenaire_confirmation", methods={"GET"})
     * @param $id
     * @param Partenaire $parent
     * @return Response
     */
    public function confirmation($id,Partenaire $parent): Response
    {
        return $this->render('_admin/modal/confirmation.html.twig',[
            'id'=>$id,
            'action'=>'partenaire',
        ]);
    }

    /**
     * @Route("/partenaire", name="partenaire")
     * @param Request $request
     * @param DataTableFactory $dataTableFactory
     * @param PartenaireRepository $partenaireRepository
     * @return Response
     */
    public function index(Request $request,
                          DataTableFactory $dataTableFactory,
                          PartenaireRepository $partenaireRepository): Response
    {

        $table = $dataTableFactory->create();

        $user = $this->getUser();

        $requestData = $request->request->all();

        $offset = intval($requestData['start'] ?? 0);
        $limit = intval($requestData['length'] ?? 10);

        $searchValue = $requestData['search']['value'] ?? null;


        $totalData = $partenaireRepository->countAll();
        $totalFilteredData = $partenaireRepository->countAll($searchValue);
        $data = $partenaireRepository->getAll($limit, $offset,  $searchValue);

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
                                'url' => $this->generateUrl('partenaire_edit', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-edit'
                                , 'attrs' => ['class' => 'btn-success']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['edit'];
                                })
                            ],
                            'details' => [
                                'url' => $this->generateUrl('partenaire_show', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-eye'
                                , 'attrs' => ['class' => 'btn-primary']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['details'];
                                })
                            ],
                            /*   'delete' => [
                                   'url' => $this->generateUrl('partenaire_delete', ['id' => $value])
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

        return $this->render('_admin/partenaire/index.html.twig', ['datatable' => $table, 'titre' => 'Liste des partenaires']);
    }
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security->getUser()->getUserIdentifier();

    }

    /**
     * @Route("/partenaire/new", name="partenaire_new", methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function new(Request $request,UploaderHelper $uploaderHelper, FormError $formError, EntityManagerInterface  $em): Response
    {


        $partenaire = new Partenaire();
        $form = $this->createForm(PartenaireType::class,$partenaire, [
            'method' => 'POST',
            'action' => $this->generateUrl('partenaire_new')
        ]);

        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if($form->isSubmitted())
        {

            $statut = 1;
            $response = [];
            $redirect = $this->generateUrl('partenaire');
            $uploadedFile = $form['logo']->getData();
            //dd($format);
            if ($form->isValid()) {


//dd($uploadedFile);
                if ($uploadedFile) {
                    $newFilename = $uploaderHelper->uploadImage($uploadedFile);
                    $partenaire->setLogo($newFilename);
                }
                $partenaire->setActive(1);
                $em->persist($partenaire);
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

        return $this->render('_admin/partenaire/new.html.twig', [
            'partenaire' => $partenaire,
            'form' => $form->createView(),
            'titre' => 'Partenaire',
        ]);
    }

    /**
     * @Route("/partenaire/{id}/edit", name="partenaire_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Partenaire $partenaire
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function edit(Request $request,UploaderHelper $uploaderHelper, FormError $formError, Partenaire $partenaire, EntityManagerInterface  $em): Response
    {

        $form = $this->createForm(PartenaireType::class,$partenaire, [
            'method' => 'POST',
            'action' => $this->generateUrl('partenaire_edit',[
                'id'=>$partenaire->getId(),
            ])
        ]);
        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if($form->isSubmitted())
        {
            $statut = 1;
            $response = [];
            $redirect = $this->generateUrl('partenaire');
            $uploadedFile = $form['logo']->getData();
            if($form->isValid()){
                if ($uploadedFile) {
                    $newFilename = $uploaderHelper->uploadImage($uploadedFile);
                    $partenaire->setLogo($newFilename);
                }

                $em->persist($partenaire);
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

        return $this->render('_admin/partenaire/edit.html.twig', [
            'partenaire' => $partenaire,
            'form' => $form->createView(),
            'titre' => 'Partenaire',
        ]);
    }

    /**
     * @Route("/partenaire/{id}/show", name="partenaire_show", methods={"GET"})
     * @param partenaire $partenaire
     * @return Response
     */
    public function show(partenaire $partenaire): Response
    {
        $form = $this->createForm(PartenaireType::class,$partenaire, [
            'method' => 'POST',
            'action' => $this->generateUrl('partenaire_show',[
                'id'=>$partenaire->getId(),
            ])
        ]);

        return $this->render('_admin/partenaire/voir.html.twig', [
            'partenaire' => $partenaire,
            'titre' => 'Partenaire',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/partenaire/{id}/active", name="partenaire_active", methods={"GET"})
     * @param $id
     * @param Partenaire $partenaire
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function active($id,Partenaire $partenaire, EntityManagerInterface $entityManager): Response
    {

        if ($partenaire->getActive() == 1){

            $partenaire->setActive(0);

        }else{

            $partenaire->setActive(1);

        }
        $entityManager->persist($partenaire);
        $entityManager->flush();
        return $this->json([
            'code'=>200,
            'message'=>'ça marche bien',
            'active'=>$partenaire->getActive(),
        ],200);


    }


    /**
     * @Route("/partenaire/{id}/delete", name="partenaire_delete", methods={"POST","GET","DELETE"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param Partenaire $partenaire
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $em,Partenaire $partenaire): Response
    {


        $form = $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'partenaire_delete'
                    ,   [
                        'id' => $partenaire->getId()
                    ]
                )
            )
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->remove($partenaire);
            $em->flush();

            $redirect = $this->generateUrl('partenaire');

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
        return $this->render('_admin/partenaire/delete.html.twig', [
            'partenaire' => $partenaire,
            'form' => $form->createView(),
        ]);
    }

}
