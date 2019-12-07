<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LazyEntityCommand extends CommandMiddleware
{
    private $entity;
    private $namespace;
    private $sonataAdmin;

    /**
     * The name of the command (the part after "bin/console")
     * The command must be registered in src/Resources/config/services.yaml
     *
     * Usage:
     *      clear; php bin/console aurora:lazy.entity --verbose --namespace=App --sonataAdmin=false --entity=Brand
     *      clear; php bin/console aurora:lazy.entity --verbose --namespace=App --sonataAdmin=true  --entity=Brand
     *
     * @var string
     */
    protected static $defaultName = 'aurora:lazy.entity';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Generate setters and getter for an entity')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED)
            ->addOption('sonataAdmin', null, InputOption::VALUE_REQUIRED)
            ->addOption('entity', null, InputOption::VALUE_REQUIRED)
            ->setHelp('This command allows you to autogenerate files and methods for an entity...');;
    }

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container      = $container;
        $this->kernelRootDir  = $this->container->getParameter('kernel.project_dir');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;
        $this->em     = $this->container->get('doctrine')->getManager();

        $this->namespace   = $this->input->getOption('namespace');
        $this->sonataAdmin = (in_array((string)$this->input->getOption('sonataAdmin'), ['1', 'true']) ? true : false);
        $this->entity      = $this->input->getOption('entity');

        if (null == $this->namespace) {
            throw new \Exception('Option --namespace is not set.');
        }

        if (null == $this->entity) {
            throw new \Exception('Option --entity is not set.');
        }

        $this->generateSettersGetters();

        return 0;
    }

    private function generateSettersGetters()
    {
        $overwrite = 1;

        $eDir = $this->kernelRootDir . "/src/Entity/";

        $directory = new \RecursiveDirectoryIterator($eDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator  = new \RecursiveIteratorIterator($directory);
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

            //echo $entity .' | '; echo  $info->getPathname(). "\n";die;

            $className = "{$this->namespace}\Entity\\" . str_replace('/', '\\', $entityDir) . "{$entity}";

            if (\file_exists($file)) {
                $fileContent = \file_get_contents($file);
            } else {
                throw new \Exception("The class {$className}() DOESN'T exists.");
            }

            // DEBUG
            if (0 && \preg_match('/function __construct/i', $fileContent)) {
                throw new \Exception("The class {$className}() CANNOT be regenerated: [__construct()] is present.");
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

                if (in_array($prop->name, ['id', 'createdAt', 'updatedAt', 'crondAt'])) {
                    $traints = $reflect->getTraits();
                    if ('id' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\Identifiable', 'App\Entity\Supers\IdentifiableTrait']), $traints)) {
                        continue;
                    }

                    if ('createdAt' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\Temporal', 'App\Entity\Supers\TemporalTrait', 'App\Entity\Supers\TemporalCreatedTrait']), $traints)) {
                        continue;
                    }

                    if ('updatedAt' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\Temporal', 'App\Entity\Supers\TemporalTrait']), $traints)) {
                        continue;
                    }

                    if ('crondAt' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\TemporalCrond', 'App\Entity\Supers\TemporalCrondTrait']), $traints)) {
                        continue;
                    }
                }

                if (in_array($returnType, ['DateTime'])) {
                    $returnTypeGet = "\\{$returnType}";
                    $returnTypeSet = "\\{$returnType}";

                } else if (preg_match('/\[/i', $prop->getDocComment())) {
                    $returnTypeGet = 'ArrayCollection';
                    $returnTypeSet = 'ArrayCollection';

                } else if (preg_match('/ArrayCollection/i', $prop->getDocComment())) {
                    $returnTypeGet = '\Doctrine\Common\Collections\ArrayCollection';
                    $returnTypeSet = '\Doctrine\Common\Collections\ArrayCollection';

                } else if (preg_match('/PersistentCollection/i', $prop->getDocComment())) {
                    $returnTypeGet = '\Doctrine\ORM\PersistentCollection';
                    $returnTypeSet = 'ArrayCollection';

                } else if (preg_match("/{$this->namespace}\\\Entity/i", $prop->getDocComment())) {
                    $returnTypeGet = "\\" . $returnType;
                    $returnTypeSet = "\\" . $returnType;
                } else {
                    $returnTypeGet = "{$returnType}";
                    $returnTypeSet = "{$returnType}";
                }

                /*
                if(in_array($paramType[1], ['DateTime'])) {
                    $paramType[1] = "\\{$paramType[1]}";
                }
                */

                $returnThis = "\n" . \str_repeat(' ', 8) . "return \$this;";

                $getDoc = "\n\n\t/**";
                $getDoc .= "\n\t * @return " . $returnTypeGet;
                $getDoc .= "\n\t */";

                $setDoc = "\n\n\t/**";
                $setDoc .= "\n\t * @param {$returnTypeSet} \${$prop->name}";
                $setDoc .= "\n\t * @return {$reflect->getShortName()}";
                $setDoc .= "\n\t */";

                $returnTypeGetCanBeBeNull = ($canBeNull ? ': ?' : ': ');
                $returnTypeSetCanBeBeNull = ($canBeNull ? "?{$returnTypeSet}" : "{$returnTypeSet}");

                //$returnTypeFull = ($returnType == 'ArrayCollection' ? '' : ": ?{$returnType}");

                $xml .= "\n" . <<<EOT
    {$getDoc}
    public function get{$function}(){$returnTypeGetCanBeBeNull}{$returnTypeGet}
    {
        return \$this->{$prop->name};
    }{$setDoc}
    public function set{$function}({$returnTypeSetCanBeBeNull} \$$prop->name): {$reflect->getShortName()}
    {
        \$this->{$prop->name} = \$$prop->name;{$returnThis}
    }
EOT;

                if('array' == $returnType) {
                    $xml .= "\n" . <<<EOT
    {$setDoc}
    public function append{$function}({$returnType} \$$prop->name): {$reflect->getShortName()}
    {
        \$this->{$prop->name} = (is_array(\$this->meta) ? array_merge(\$this->$prop->name, \$$prop->name) : \$$prop->name);{$returnThis}
    }
EOT;
                }
            }

            $xml .= $toString;

            if ($overwrite) {

                //\preg_match_all('/private \$(.*);/i', $fileContent, $matches);
                //preg_match("/(?s)private(?!.*private).+;/", $fileContent, $matches);
                preg_match_all('/private[\s\w]*\s\$(.*);/', $fileContent, $matches);

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

                if (!empty($newContent) && \file_put_contents($file, \rtrim($newContent))) {
                    echo "The class {$className}() has been regenerated.\n";
                }

            } else {
                echo $xml;
            }

            ############################################################################################################
            ##   Entity Repository   ###################################################################################

            $entityRepositoryDir = $this->kernelRootDir . "/src/Repository/";

            if (!is_dir($entityRepositoryDir)) {
                mkdir($directory, 0777, true);
            }

            if (!file_exists($entityRepositoryFile = $entityRepositoryDir . $entityDir . $entity . 'Repository.php')) {
                $entityRepositoryFileContent = <<<EOT
<?php

namespace {$this->namespace}\Repository{$namespaceDir};

// Symfony

// Doctrine
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

// App
use App\Entity\\{$entity};

/**
 * Class {$entity}Repository
 */
class {$entity}Repository extends ServiceEntityRepository
{
    use \Sindla\Bundle\AuroraBundle\Repository\Traits\BaseRepository;
    
    public function __construct(ManagerRegistry \$registry)
    {
        parent::__construct(\$registry, {$entity}::class);
    }
}
EOT;
                if (\file_put_contents($entityRepositoryFile, $entityRepositoryFileContent)) {
                    echo "The class {$entity}Repository() has been regenerated.\n";
                }
            }

            ############################################################################################################
            ##   Sonata Admin   ########################################################################################

            if ($this->sonataAdmin) {
                if (file_exists($sonataAdminFile = $this->kernelRootDir . "/Admin/" . $entityDir . $entity . 'Admin.php')) {
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