<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Entity\Fichier;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Repository\DocumentCourrierRepository;
use App\Service\ActionRender;
use App\Service\FormError;
use App\Service\Omines\Adapter\ArrayAdapter;
use App\Service\UploaderHelper;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/admin")
 */
class ProduitController extends AbstractController
{
    use FileTrait;

    private const UPLOAD_PATH = 'produit';

    /**
     * @Route("/produit", name="produit")
     * @param Request $request
     * @param DataTableFactory $dataTableFactory
     * @param ProduitRepository $produitRepository
     * @return Response
     */
    public function index(Request $request,
                          DataTableFactory $dataTableFactory,
                          ProduitRepository $produitRepository): Response
    {

        $table = $dataTableFactory->create();

        $user = $this->getUser();

        $requestData = $request->request->all();

        $offset = intval($requestData['start'] ?? 0);
        $limit = intval($requestData['length'] ?? 10);

        $searchValue = $requestData['search']['value'] ?? null;



        $totalData = $produitRepository->countAll();
        $totalFilteredData = $produitRepository->countAll($searchValue);
        $data = $produitRepository->getAll($limit, $offset,  $searchValue);

//dd($data);


        $table->createAdapter(ArrayAdapter::class, [
            'data' => $data,
            'totalRows' => $totalData,
            'totalFilteredRows' => $totalFilteredData
        ]) ->setName('dt_');
        ;


        $table->add('libelle', TextColumn::class, ['label' => 'Libelle', 'className' => 'w-100px'])
            ->add('date_ajout', DateTimeColumn::class, ['label' => 'Date ajout', 'format' => 'd-m-Y'])
            ->add('categorie', TextColumn::class, ['label' => 'Catégorie', 'className' => 'w-100px'])

        ;


        $renders = [
            'edit' =>  new ActionRender(function () {
                return true;
            }),
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
                                'url' => $this->generateUrl('produit_edit', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-edit'
                                , 'attrs' => ['class' => 'btn-success']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['edit'];
                                })
                            ],
                            'details' => [
                                'url' => $this->generateUrl('produit_show', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-eye'
                                , 'attrs' => ['class' => 'btn-primary']
                                , 'render' => new ActionRender(function () use ($renders) {
                                    return $renders['details'];
                                })
                            ],
                         /*   'delete' => [
                                'url' => $this->generateUrl('produit_delete', ['id' => $value])
                                , 'ajax' => true
                                , 'icon' => '%icon% fe fe-trash-2'
                                , 'target' => '#smallmodal'
                                , 'attrs' => ['class' => 'btn-danger', 'title' => 'Suppression']

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

        return $this->render('_admin/produit/index.html.twig', ['datatable' => $table, 'titre' => 'Liste des produits']);
    }



    /**
     * @Route("/archive/{id}/produit", name="produit_archive", methods={"GET"})
     * @param $id
     * @param DocumentCourrierRepository $repository
     * @return Response
     */
    public  function  archive($id, DocumentCourrierRepository $repository){


        return $this->render('_admin/produit/archive.html.twig', [
            'titre'=>'Arrive',
            'data'=>$repository->getFichier($id),

        ]);
    }

    /**
     * @Route("/courier/{id}/show", name="produit_show", methods={"GET"})
     * @param Produit $produit
     * @return Response
     */
    public function show(Produit $produit,$id,ProduitRepository $repository): Response
    {
        //$type = $produit->getType();

        $form = $this->createForm(ProduitType::class, $produit, [

            'method' => 'POST',
            'action' => $this->generateUrl('produit_show', [
                'id' => $produit->getId(),
            ])
        ]);

        return $this->render('_admin/produit/voir.html.twig', [
            'titre'=>'produit',
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/produit/new", name="produit_new", methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param UploaderHelper $uploaderHelper
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em,FormError $formError, UploaderHelper $uploaderHelper,ProduitRepository $repository): Response
    {


        $produit = new Produit();



        $form = $this->createForm(ProduitType::class, $produit, [
            'method' => 'POST',
            'doc_options' => [
                'uploadDir' => $this->getUploadDir(self::UPLOAD_PATH, true),
                'attrs' => ['class' => 'filestyle'],
            ],
            'action' => $this->generateUrl('produit_new')
        ]);


        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();

        if ($form->isSubmitted()) {
            $statut = 1;
            $redirect = $this->generateUrl('produit');

            if ($form->isValid()) {

                $produit->setActive(1);
                $produit->setDateAjout(new \DateTime());
                $em->persist($produit);
                $em->flush();
                $data = true;
                $message = 'Opération effectuée avec succès';
                $statut = 1;
                $this->addFlash('success', $message);

            } else {
                $message = $formError->all($form);
                $statut = 0;
                if (!$isAjax) {
                    $this->addFlash('warning', $message);
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

        return $this->render('_admin/produit/new.html.twig', [
            'titre'=>'produit',
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/produit/{id}/edit", name="produit_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Produit $produit
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function edit(Request $request, Produit $produit,FormError $formError, EntityManagerInterface $em,$id,ProduitRepository $repository): Response
    {


        $form = $this->createForm(ProduitType::class, $produit, [
            'method' => 'POST',
            'doc_options' => [
                'uploadDir' => $this->getUploadDir(self::UPLOAD_PATH, true),
                'attrs' => ['class' => 'filestyle'],
            ],
            'action' => $this->generateUrl('produit_edit', [
                'id' => $produit->getId(),
            ])
        ]);
        $form->handleRequest($request);
        $data = null;
        $isAjax = $request->isXmlHttpRequest();
        // $type = $form->getData()->getType();
        if ($form->isSubmitted()) {

            $redirect = $this->generateUrl('produit');


            if ($form->isValid()) {

                $em->persist($produit);
                $em->flush();

                $data = true;
                $message = 'Opération effectuée avec succès';
                $statut = 1;
                $this->addFlash('success', $message);
                /*  foreach ($produit->getDocuments() as $document) {
                      $files[$document->getDocHash()] = $this->generateUrl('fichier_index', ['id' => $document->getFichier()->getId()]);
                  }*/
            } else {
                $message = $formError->all($form);
                $statut = 0;
                if (!$isAjax) {
                    $this->addFlash('warning', $message);
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

        return $this->render('_admin/produit/edit.html.twig', [
            'titre'=>'produit',
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/accuse/{id}", name="produit_accuse_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Produit $produit
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function accuse(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProduitType::class, $produit, [
            'method' => 'POST',
            'action' => $this->generateUrl('produit_accuse_edit', [
                'id' => $produit->getId(),
            ])
        ]);

        $file = new Fichier();
        $file->setPath("");

        $produit->addFichier($file);

        $form->handleRequest($request);

        $isAjax = $request->isXmlHttpRequest();
        //  $type = $form->getData()->getType();
        if ($form->isSubmitted()) {

            $redirect = $this->generateUrl('produit');
            $brochureFile = $form->get('fichiers')->getData();

            if ($form->isValid()) {

                foreach ($brochureFile as $image) {
                    $file = new File($image->getPath());
                    $newFilename = md5(uniqid()) . '.' . $file->guessExtension();
                    // $fileName = md5(uniqid()).'.'.$file->guessExtension();
                    $file->move($this->getParameter('images_directory'), $newFilename);
                    $image->setPath($newFilename);
                }
                $em->persist($produit);
                $em->flush();

                $message = 'Opération effectuée avec succès';
                $statut = 1;
                $this->addFlash('success', $message);

            }

            if ($isAjax) {
                return $this->json(compact('statut', 'message', 'redirect'));
            } else {
                if ($statut == 1) {
                    return $this->redirect($redirect);
                }
            }
        }

        return $this->render('_admin/produit/accuse.html.twig', [
            'titre'=>"ACCUSE DE RECEPTION",
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/produit/delete/{id}", name="produit_delete", methods={"POST","GET","DELETE"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param produit $produit
     * @return Response
     */
    public function delete($id,Request $request, EntityManagerInterface $em, Produit $produit): Response
    {


        $form = $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'produit_delete'
                    , [
                        'id' => $produit->getId()
                    ]
                )
            )
            ->setMethod('DELETE')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->remove($produit);
            $em->flush();

            $redirect = $this->generateUrl('dossierActeVente');

            $message = 'Opération effectuée avec succès';

            $response = [
                'statut'   => 1,
                'message'  => $message,
                'data' => true,
                'redirect' => $redirect,
            ];

            $this->addFlash('success', $message);

            if (!$request->isXmlHttpRequest()) {
                return $this->redirect($redirect);
            } else {
                return $this->json($response);
            }


        }
        return $this->render('_admin/produit/delete.html.twig', [
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/produit/{id}/active", name="produit_active", methods={"GET"})
     * @param $id
     * @param Produit $parent
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function active($id, Produit $parent, EntityManagerInterface $entityManager): Response
    {

        if ($parent->getActive() == 1) {

            $parent->setActive(0);

        } else {

            $parent->setActive(1);

        }

        $entityManager->persist($parent);
        $entityManager->flush();
        return $this->json([
            'code' => 200,
            'message' => 'ça marche bien',
            'active' => $parent->getActive(),
        ], 200);

    }
}
