<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class LazyEntityCommand extends CommandMidleware
{
    /**
     * @var EntityManager
     */
    private $em;

    private $input;

    private $output;

    private $entity;
    private $namespace;
    private $sonataAdmin;

    /**
     * {@inheritDoc}
     *
     * Usage:
     *      clear; php bin/console aurora:lazy.entity --verbose --namespace=AppBundle --sonataAdmin=false --entity=Brand
     *      clear; php bin/console aurora:lazy.entity --verbose --namespace=AppBundle --sonataAdmin=true  --entity=Brand
     */
    protected function configure()
    {
        $this->setName('aurora:lazy.entity')

            ->setDescription('Generate setters and getter for an entity')

            // Mandatory
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED)
            ->addOption('sonataAdmin', null, InputOption::VALUE_REQUIRED)
            ->addOption('entity', null, InputOption::VALUE_REQUIRED)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input    = $input;
        $this->output   = $output;
        $this->em       = $this->getContainer()->get('doctrine')->getManager();

        $this->namespace    = $this->input->getOption('namespace');
        $this->sonataAdmin  = (in_array((string) $this->input->getOption('sonataAdmin'), ['1', 'true']) ? true : false);
        $this->entity       = $this->input->getOption('entity');

        $this->generateSettersGetters();
    }

    private function generateSettersGetters()
    {
        $overwrite = 1;
        $singleton = true;

        $eDir = $this->getContainer()->get('kernel')->getRootDir() ."/../src/{$this->namespace}/Entity/";
        $directory = new \RecursiveDirectoryIterator($eDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $fileName => $info) {

            $entity       = preg_replace('/\\.[^.\\s]{3,4}$/', '', $info->getFilename());
            $entityLower  = strtolower($entity);
            $entityDir    = '';
            $namespaceDir = '';

            if ($this->input->getOption('entity') && $this->input->getOption('entity') != $entity) {
                continue;
            }

            if (in_array($entity, ['.', '..', 'IdentifiableTrait', 'TemporalTrait'])) {
                continue;
            }

            $file = $info->getPathname();

            //echo $entity .' | '; echo  $info->getPathname(). "\n";

            $className = "{$this->namespace}\Entity\\" . str_replace('/', '\\', $entityDir) . "{$entity}";

            if (\file_exists($file)) {
                $fileContent = \file_get_contents($file);
            } else {
                echo "[!!!] The class {$className}() DOESN'T exists.\n";
                exit(0);
            }

            // DEBUG
            if (0 && \preg_match('/function __construct/i', $fileContent)) {
                echo "[!!!] The class {$className}() CANNOT be regenerated: [__construct()] is present.\n";
                exit(0);
            }

            $constructBody = false;
            preg_match('/__construct(.*?)}/is', $fileContent, $matches);
            if (!empty($matches)) {
                $constructBody = $matches[0];
            }

            require_once $file;

            $Class = new $className();

            $reflect = new \ReflectionClass($Class);

            // Get class $vars
            $props = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);

            $toString = '';
            $xml      = '';

            if ($constructBody) {
                $xml .= "\n\n" . '    public function ' . $constructBody;
            }

            foreach ($props as $prop) {

                // Method @var value. Eg: string | int | Attribute
                $paramType = $this->getDocCommentVar($prop->getDocComment());

                $ORMType = $this->getDocDocumentORMType($prop->getDocComment());

                $ORMTargetEntityFullyQualifiedName = $this->getDocDocumentORMTargetEntity($prop->getDocComment(), true);

                $ORMTargetEntity = $this->getDocDocumentORMTargetEntity($prop->getDocComment(), false);

                $returnType = $ORMTargetEntityFullyQualifiedName ? $ORMTargetEntityFullyQualifiedName : $paramType;

                $canBeNull = $this->canBeNull($prop->getDocComment());

                // DEBUG
                if (0 && preg_match("/{$this->namespace}\\\Entity\\\???/i", $prop->getDocComment())) {
                    echo $returnType . "\n\n";
                    echo $prop->getDocComment() . "\n\n";
                    print_r($paramType);
                    die;
                }

                $function = ucfirst($prop->name);

                if (preg_match("/__toString/", $prop->getDocComment())) {
                    $toString = "\n\n" . <<<EOT
    public function __toString() {
        return (\$this->get{$function}() ? (string) \$this->get{$function}() : '');
    }
EOT;
                }

                if (in_array($prop->name, ['id', 'createdAt', 'updatedAt'])) {
                    continue;
                }

                if (in_array($returnType, ['DateTime'])) {
                    $returnType = "\\{$returnType}";
                } else if (preg_match('/\[/i', $prop->getDocComment())) {
                    $returnType = 'ArrayCollection';
                } else if (preg_match('/PersistentCollection/i', $prop->getDocComment())) {
                    $returnType = '\Doctrine\ORM\PersistentCollection';
                } else if (preg_match("/{$this->namespace}\\\Entity/i", $prop->getDocComment())) {
                    $returnType = "\\" . $returnType;
                }

                /*
                if(in_array($paramType[1], ['DateTime'])) {
                    $paramType[1] = "\\{$paramType[1]}";
                }
                */

                $singleton = $singleton ? "\n" . \str_repeat(' ', 8) . "return \$this;" : '';

                $getDoc = "\n\n\t/**\n\t *";
                $getDoc .= " @return " . $returnType;
                $getDoc .= "\n\t */";
                $setDoc = $singleton ? "\n\n\t/**\n\t * @param {$returnType} \${$prop->name}\n\t * @return {$reflect->getShortName()}\n\t */" : "\n";

                $returnTypeFull = ($returnType == 'ArrayCollection' ? '' : ": ?{$returnType}");

                $returnType = ($canBeNull ? "?{$returnType}" : $returnType);

                $xml .= "\n" . <<<EOT
    {$getDoc}
    public function get{$function}()$returnTypeFull
    {
        return \$this->{$prop->name};
    }{$setDoc}
    public function set{$function}({$returnType} \$$prop->name): {$reflect->getShortName()}
    {
        \$this->{$prop->name} = \$$prop->name;{$singleton}
    }
EOT;
            }

            $xml .= $toString;

            if ($overwrite) {

                \preg_match_all('/private \$(.*);/i', $fileContent, $matches);
                //preg_match("/(?s)private(?!.*private).+;/", $fileContent, $matches);

                $newContent = '';
                if ($matches[0]) {
                    $x         = end($matches[0]);
                    $fileLines = \file($file);

                    foreach ($fileLines as $fileLine) {
                        if (!\strpos($fileLine, $x)) {
                            $newContent .= $fileLine;
                        } else {
                            $newContent .= $fileLine;
                            break;
                        }
                    }

                    $newContent = \str_replace($x, $x . $xml . "\n}", $newContent);
                }

                $newContent = str_replace("\t", "    ", $newContent);
                $newContent = preg_replace("/([ \t]*\n){3,}/", "\n\n", $newContent);

                if (\file_put_contents($file, \rtrim($newContent))) {
                    echo "The class {$className}() has been regenerated.\n";
                }

            } else {
                echo $xml;
            }

            ############################################################################################################
            ##   Entity Repository   ###################################################################################

            if (!file_exists($entityRepositoryFile = $this->getContainer()->get('kernel')->getRootDir() . "/../src/{$this->namespace}/EntityRepository/" . $entityDir . $entity . 'Repository.php')) {
                $entityRepositoryFileContent = <<<EOT
<?php

namespace {$this->namespace}\EntityRepository{$namespaceDir};

use {$this->namespace}\EntityRepository\Supers\BaseRepository;

/**
 * Class {$entity}Repository
 */
class {$entity}Repository extends BaseRepository
{

}
EOT;
                file_put_contents($entityRepositoryFile, $entityRepositoryFileContent);
            }

            ############################################################################################################
            ##   Sonata Admin   ########################################################################################

            if ($this->sonataAdmin) {
                if (file_exists($sonataAdminFile = $this->getContainer()->get('kernel')->getRootDir() . "/../src/{$this->namespace}/Admin/" . $entityDir . $entity . 'Admin.php')) {
                    $sonataAdminFileContent = file_get_contents($sonataAdminFile);
                }

                if (!isset($sonataAdminFileContent) || (isset($sonataAdminFileContent) && preg_match('/@autogenerate/', $sonataAdminFileContent))) {
                    $sonataAdminFileContent = <<<EOT
    <?php
    namespace {$this->namespace}\Admin{$namespaceDir};
    
    use Sonata\AdminBundle\Admin\AbstractAdmin;
    use Sonata\AdminBundle\Datagrid\ListMapper;
    use Sonata\AdminBundle\Datagrid\DatagridMapper;
    use Sonata\AdminBundle\Form\FormMapper;
    
    /**
     * Auto-generated file
     * This file is generated by running command [aurora:lazy.entity]
     * If you want to stop to auto-generate this file, remove the following addannotation
     *
     * Route: admin_app_{$entityLower}_list | admin_app_{$entityLower}_edit | admin_app_{$entityLower}_delete | admin_app_{$entityLower}_show
     * 
     * @autogenerate
     */
    class {$entity}Admin extends AbstractAdmin
    {
        // Add
        protected function configureFormFields(FormMapper \$formMapper)
        {
EOT;
                    foreach ($props as $prop) {
                        if (!in_array($prop->name, ['id', 'createdAt', 'updatedAt'])) {
                            $sonataAdminFileContent .= "\n\t\t\$formMapper->add('{$prop->name}');";
                        }
                    }

                    $sonataAdminFileContent .= <<<EOT
    \n\t}
        
        // Filter
        protected function configureDatagridFilters(DatagridMapper \$datagridMapper)
        {
EOT;
                    foreach ($props as $prop) {
                        if ($prop->name == 'id') {
                            $sonataAdminFileContent .= "\n\t\t\$datagridMapper->add('{$prop->name}');";
                        }
                    }

                    foreach ($props as $prop) {
                        if (!in_array($prop->name, ['id'])) {
                            $sonataAdminFileContent .= "\n\t\t\$datagridMapper->add('{$prop->name}');";
                        }
                    }

                    $sonataAdminFileContent .= <<<EOT
    \n\t}
        
        // List (table list)
        protected function configureListFields(ListMapper \$listMapper)
        {
EOT;
                    // ID first
                    foreach ($props as $prop) {
                        if ($prop->name == 'id') {
                            $sonataAdminFileContent .= "\n\t\t\$listMapper->addIdentifier('{$prop->name}');";
                        }
                    }

                    // other, after ID
                    foreach ($props as $prop) {
                        if (!in_array($prop->name, ['id'])) {
                            $sonataAdminFileContent .= "\n\t\t\$listMapper->add('{$prop->name}');";
                        }
                    }
                    $sonataAdminFileContent .= "\n\t\t\$listMapper->add(
                        '_action',
                        'actions',
                        [
                            'actions' => [
                                'show' => [],
                                'edit' => [],
                                'delete' => [],
                            ]
                        ]
                    );";
                    $sonataAdminFileContent .= <<<EOT
    \n\t}
    }
EOT;
                    $sonataAdminFileContent = str_replace("\t", "    ", $sonataAdminFileContent);
                    file_put_contents($sonataAdminFile, $sonataAdminFileContent);
                }

                unset($toString, $sonataAdminFileContent);
            }

            ############################################################################################################
            ############################################################################################################
        }
    }
}