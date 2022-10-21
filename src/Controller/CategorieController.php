<?php

namespace App\Controller;

use App\Repository\TableInfoTrait;
use App\Service\Services;
use App\Entity\Categorie;
use App\Service\FormError;
use App\Form\CategorieType;
use App\Service\ActionRender;
use App\Repository\CategorieRepository;
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
 * il s'agit du categorie des module
 */
class CategorieController extends AbstractController
{
    use TableInfoTrait;
    /**
     * @Route("/categorie/{id}/confirmation", name="categorie_confirmation", methods={"GET"})
     * @param $id
     * @param Categorie $parent
     * @return Response
     */
    public function confirmation($id,Categorie $parent): Response
    {
        return $this->render('_admin/modal/confirmation.html.twig',[
            'id'=>$id,
            'action'=>'categorie',
        ]);
    }

    /**
     * @Route("/categorie", name="categorie")
     * @param Request $request
     * @param DataTableFactory $dataTableFactory
     * @param CategorieRepository $categorieRepository
     * @return Response
     */
    public function index(Request $request,
                          DataTableFactory $dataTableFactory,
                          CategorieRepository $categorieRepository): Response
    {

        $table = $dataTableFactory->create();

        $user = $this->getUser();

        $requestData = $request->request->all();

        $offset = intval($requestData['start'] ?? 0);
        $limit = intval($requestData['length'] ?? 10);

        $searchValue = $requestData['search']['value'] ?? null;



        $totalData = $categorieRepository->countAll();
        $totalFilteredData = $categorieRepository->countAll($searchValue);
        $data = $categorieRepository->getAll($limit, $offset,  $searchValue);

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
                                'url' => $this->generateUrl('categorie_edit', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-edit'
                                , 'attrs' => ['class' => 'btn-success']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['edit'];
                                })
                            ],
                            'details' => [
                                'url' => $this->generateUrl('categorie_show', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-eye'
                                , 'attrs' => ['class' => 'btn-primary']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['details'];
                                })
                            ],
                         /*   'delete' => [
                                'url' => $this->generateUrl('categorie_delete', ['id' => $value])
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

        return $this->render('_admin/categorie/index.html.twig', ['datatable' => $table, 'titre' => 'Liste des categories']);
    }
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security->getUser()->getUserIdentifier();

    }

    /**
     * @Route("/categorie/new", name="categorie_new", methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function new(Request $request,UploaderHelper $uploaderHelper, FormError $formError, EntityManagerInterface  $em): Response
    {


        $categorie = new Categorie();
        $form = $this->createForm(CategorieType::class,$categorie, [
            'method' => 'POST',
            'action' => $this->generateUrl('categorie_new')
        ]);

        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if($form->isSubmitted())
        {

            $statut = 1;
            $response = [];
            $redirect = $this->generateUrl('categorie');
            $uploadedFile = $form['image']->getData();
           //dd($format);
            if ($form->isValid()) {


//dd($uploadedFile);
                if ($uploadedFile) {
                    $newFilename = $uploaderHelper->uploadImage($uploadedFile);
                    $categorie->setImage($newFilename);
                }
                $categorie->setActive(1);
                $em->persist($categorie);
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

        return $this->render('_admin/categorie/new.html.twig', [
            'categorie' => $categorie,
            'form' => $form->createView(),
            'titre' => 'Categorie',
        ]);
    }

    /**
     * @Route("/categorie/{id}/edit", name="categorie_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Categorie $categorie
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function edit(Request $request,UploaderHelper $uploaderHelper, FormError $formError, Categorie $categorie, EntityManagerInterface  $em): Response
    {

        $form = $this->createForm(CategorieType::class,$categorie, [
            'method' => 'POST',
            'action' => $this->generateUrl('categorie_edit',[
                'id'=>$categorie->getId(),
            ])
        ]);
        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if($form->isSubmitted())
        {
            $statut = 1;
            $response = [];
            $redirect = $this->generateUrl('categorie');
            $uploadedFile = $form['image']->getData();
            if($form->isValid()){
                if ($uploadedFile) {
                    $newFilename = $uploaderHelper->uploadImage($uploadedFile);
                    $categorie->setImage($newFilename);
                }

                $em->persist($categorie);
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

        return $this->render('_admin/categorie/edit.html.twig', [
            'categorie' => $categorie,
            'form' => $form->createView(),
            'titre' => 'Categorie',
        ]);
    }

    /**
     * @Route("/categorie/{id}/show", name="categorie_show", methods={"GET"})
     * @param categorie $categorie
     * @return Response
     */
    public function show(categorie $categorie): Response
    {
        $form = $this->createForm(CategorieType::class,$categorie, [
            'method' => 'POST',
            'action' => $this->generateUrl('categorie_show',[
                'id'=>$categorie->getId(),
            ])
        ]);

        return $this->render('_admin/categorie/voir.html.twig', [
            'categorie' => $categorie,
            'titre' => 'Categorie',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/categorie/{id}/active", name="categorie_active", methods={"GET"})
     * @param $id
     * @param Categorie $categorie
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function active($id,Categorie $categorie, EntityManagerInterface $entityManager): Response
    {

        if ($categorie->getActive() == 1){

            $categorie->setActive(0);

        }else{

            $categorie->setActive(1);

        }
        $entityManager->persist($categorie);
        $entityManager->flush();
        return $this->json([
            'code'=>200,
            'message'=>'ça marche bien',
            'active'=>$categorie->getActive(),
        ],200);


    }


    /**
     * @Route("/categorie/{id}/delete", name="categorie_delete", methods={"POST","GET","DELETE"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param Categorie $categorie
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $em,Categorie $categorie): Response
    {


        $form = $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'categorie_delete'
                    ,   [
                        'id' => $categorie->getId()
                    ]
                )
            )
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->remove($categorie);
            $em->flush();

            $redirect = $this->generateUrl('categorie');

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
        return $this->render('_admin/categorie/delete.html.twig', [
            'categorie' => $categorie,
            'form' => $form->createView(),
        ]);
    }

}
