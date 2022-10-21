<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', EntityType::class, [
                'required' => false,
                'label' => false,
                'class' => Categorie::class,
                'placeholder' => 'Selectionnez une catégorie',
                'choice_label' => 'libelle',
                'attr'=>['class' =>'form-control has-select2']
            ])
            ->add('libelle', null, ['label' => 'Titre'])
            ->add('description', null, ['label' => 'Titre'])
            ->add('image', CollectionType::class, [
                 'entry_type' => ImageType::class,
                 'entry_options' => [
                     'label' => false,
                     'doc_options' => $options['doc_options'],
                     'doc_required' => $options['doc_required']
                 ],
                 'allow_add' => true,
                 'label' => false,
                 'by_reference' => false,
                 'allow_delete' => true,
                 'prototype' => true,

             ]);
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
            'doc_required' => false,
            'doc_options' => [],
        ]);
        $resolver->setRequired('doc_required');
        $resolver->setRequired('doc_options');
    }
}
