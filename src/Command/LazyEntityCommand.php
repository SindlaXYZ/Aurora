<?php

namespace Sindla\Bundle\AuroraBundle\Command;

// Symfony
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Doctrine
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Column;

// Sindla
use Sindla\Bundle\AuroraBundle\Doctrine\Annotation\Aurora;

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
        parent::__construct(self::$defaultName);
        $this->container     = $container;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var InputInterface */
        $this->input = $input;

        /** @var OutputInterface */
        $this->output = $output;

        /** @var SymfonyStyle io */
        $this->io = new SymfonyStyle($this->input, $this->output);

        $this->em = $this->container->get('doctrine')->getManager();

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

        return Command::SUCCESS;
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
                $func           = new \ReflectionMethod($className, '__construct');
                $filename       = $func->getFileName();
                $start_line     = $func->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
                $end_line       = $func->getEndLine();
                $length         = $end_line - $start_line;
                $source         = file($filename);
                $body           = implode("", array_slice($source, $start_line, $length));
                $constructBody  = $body;
            }

            require_once $file;

            $Class = new $className();

            $reflect = new \ReflectionClass($Class);

            $reader           = new AnnotationReader();
            $classAnnotations = $reader->getClassAnnotations($reflect);

            // Get class [public|protected|private $vars = ..]
            $props = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);

            // Get class [const X_Y = ..]
            $constants = $reflect->getConstants();

            $toString = '';
            $xml      = '';

            if ($constructBody) {
                //$xml .= "\n\n" . '    public function ' . $constructBody;
                $xml .= "\n\n". $constructBody;
            }

            foreach ($props as $prop) {

                $manyToMany = false;

                /*
                echo "\n" . str_repeat('-', 50) . "\n";
                print_r($prop);
                continue;
                */

                //print_r($classAnnotations);die;

                $auroraAnnotation  = new Aurora();
                $reflectionMethod  = $reflect->getProperty($prop->name);
                $methodAnnotations = $reader->getPropertyAnnotations($reflectionMethod);

                //die(print_r($methodAnnotations));

                foreach ($methodAnnotations as $annotation) {
                    if ($annotation instanceof \Doctrine\ORM\Mapping\ManyToMany) {
                        $manyToMany = [
                            'orm'        => $annotation,
                            'reflection' => new \ReflectionClass($annotation->targetEntity)
                        ];
                    } else if ($annotation instanceof Column) {

                    } else if ($annotation instanceof Aurora) {
                        /** @var Aurora $auroraAnnotation */
                        $auroraAnnotation = $annotation;
                    }
                }

                if (0 && $manyToMany) {
                    echo $manyToMany['reflection']->getShortName();
                    die(print_r($manyToMany));
                }


                // Method @var value. Eg: string | int | Attribute
                $paramType = $this->getDocCommentVar($prop->getDocComment());

                $ORMType = $this->getDocDocumentORMType($prop->getDocComment());

                $ORMTargetEntityFullyQualifiedName = $this->getDocDocumentORMTargetEntity($prop->getDocComment(), true);

                $ORMTargetEntity = $this->getDocDocumentORMTargetEntity($prop->getDocComment(), false);

                //print_r($ORMTargetEntity);die;

                $returnType = $ORMTargetEntityFullyQualifiedName ? $ORMTargetEntityFullyQualifiedName : $paramType;

                $canBeNull = $this->canBeNull($prop->getDocComment());

                // DEBUG
                if (0) {
                    echo "\n" . str_repeat('-', 50) . "\n";
                    echo "\n RT: " . $returnType;
                    echo "\n" . $prop->getDocComment();
                    echo "\n PT: " . $paramType;
                    continue;
                }

                $function = ucfirst($prop->name);

                if (preg_match("/__toString/", $prop->getDocComment())) {
                    echo "__toString is deprecated. Use @Aurora instead.";
                }

                if ($auroraAnnotation->toSting) {
                    $toString = "\n\n" . <<<EOT
    public function __toString() {
        return (\$this->get{$function}() ? (string) \$this->get{$function}() : '');
    }
EOT;
                }

                if (in_array($prop->name, ['id', 'createdAt', 'updatedAt', 'crondAt'])) {
                    $traints = $reflect->getTraits();
                    if ('id' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\Identifiable', 'App\Entity\Supers\IdentifiableTrait']), $traints)) {
                        $this->io->comment(sprintf("Skip `%s`", $prop->name));
                        continue;
                    }

                    if ('createdAt' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\Temporal', 'App\Entity\Supers\TemporalTrait', 'App\Entity\Supers\TemporalCreatedTrait']), $traints)) {
                        $this->io->comment(sprintf("Skip `%s`", $prop->name));
                        continue;
                    }

                    if ('updatedAt' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\Temporal', 'App\Entity\Supers\TemporalTrait']), $traints)) {
                        $this->io->comment(sprintf("Skip `%s`", $prop->name));
                        continue;
                    }

                    if ('crondAt' == $prop->name && array_diff_key(array_flip(['App\Entity\Traits\TemporalCrond', 'App\Entity\Supers\TemporalCrondTrait']), $traints)) {
                        $this->io->comment(sprintf("Skip `%s`", $prop->name));
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
                    $returnTypeSet = '\Doctrine\Common\Collections\ArrayCollection';

                } else if (preg_match('/Collections\\\Collection/i', $prop->getDocComment())) {
                    $returnTypeGet = '\Doctrine\Common\Collections\Collection';
                    $returnTypeSet = '\Doctrine\Common\Collections\Collection';

                // } elseif(preg_match('/ArrayCollection/i', $prop->getDocComment()) && preg_match('/PersistentCollection/i', $prop->getDocComment()) && preg_match('/ManyToMany/', $prop->getDocComment())) {

                } else if (preg_match("/{$this->namespace}\\\Entity/i", $prop->getDocComment())) {
                    $returnTypeGet = "\\" . $returnType;
                    $returnTypeSet = "\\" . $returnType;
                } else {
                    $returnTypeGet = "{$returnType}";
                    $returnTypeSet = "{$returnType}";
                }

                // ManyToMany

                /*
                if(in_array($paramType[1], ['DateTime'])) {
                    $paramType[1] = "\\{$paramType[1]}";
                }
                */

                $returnThis  = "\n" . \str_repeat(' ', 8) . "return \$this;";
                $returnThis2 = "return \$this;";

                $getDoc = "\n\n\t/**";
                $getDoc .= "\n\t * @return " . ($canBeNull ? "?" . $returnTypeGet : $returnTypeGet);
                $getDoc .= "\n\t */";

                $setDoc = "\n\n\t/**";
                if ($manyToMany && !empty($manyToMany['orm']->inversedBy)) {
                    $setDoc .= "\n\t * @param " . ($canBeNull ? '?' : '') . "{$manyToMany['reflection']->getShortName()} \${$manyToMany['reflection']->getShortName()}";
                    $setDoc .= "\n\t * @return boolean|{$reflect->getShortName()}";
                } else {
                    $setDoc .= "\n\t * @param " . ($canBeNull ? '?' : '') . "{$returnTypeSet} \${$prop->name}";
                    $setDoc .= "\n\t * @return {$reflect->getShortName()}";
                }
                $setDoc .= "\n\t */";

                $returnTypeGetCanBeBeNull = ($canBeNull ? ': ?' : ': ');
                $returnTypeSetCanBeBeNull = ($canBeNull ? "?{$returnTypeSet}" : "{$returnTypeSet}");

                //$returnTypeFull = ($returnType == 'ArrayCollection' ? '' : ": ?{$returnType}");

                // If get...() method does not exists on parent class
                if (!$reflect->getParentClass() || ($reflect->getParentClass() && !$reflect->getParentClass()->hasMethod("get{$function}"))) {
                    $xml .= "\n" . <<<EOT
    {$getDoc}
    public function get{$function}(){$returnTypeGetCanBeBeNull}{$returnTypeGet}
    {
        return \$this->{$prop->name};
    }
EOT;
                }

                // If set...() method does not exists on parent class
                if (!$reflect->getParentClass() || ($reflect->getParentClass() && !$reflect->getParentClass()->hasMethod("set{$function}"))) {
                    $xml .= "\n" . <<<EOT
    {$setDoc}
    public function set{$function}({$returnTypeSetCanBeBeNull} \$$prop->name): {$reflect->getShortName()}
    {
        \$this->{$prop->name} = \${$prop->name};{$returnThis}
    }
EOT;
                }


                // Many to Many
                if ($manyToMany && !empty($manyToMany['orm']->inversedBy)) {
                    $xml .= "\n" . <<<EOT
    {$setDoc}
    public function set{$function}Add({$manyToMany['reflection']->getShortName()} \${$manyToMany['reflection']->getShortName()})
    {
        if(\$this->{$prop->name}->contains(\${$manyToMany['reflection']->getShortName()})) {
            return false;
        }

        \$this->{$prop->name}->add(\${$manyToMany['reflection']->getShortName()});
        return \$this;
    }
EOT;

                    $xml .= "\n" . <<<EOT
    {$setDoc}
    public function set{$function}Remove({$manyToMany['reflection']->getShortName()} \${$manyToMany['reflection']->getShortName()})
    {
        if(!\$this->{$prop->name}->contains(\${$manyToMany['reflection']->getShortName()})) {
            return false;
        }

        \$this->{$prop->name}->removeElement(\${$manyToMany['reflection']->getShortName()});
        return \$this;
    }
EOT;
                }

                // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -
                // -- bitwise (+bitwiseConst) -- - - - - - - - - - - - - - - - - - - - - - - - - -

                if ($auroraAnnotation->bitwise || preg_match('/__bitwise/', $prop->getDocComment()) || $auroraAnnotation->bitwiseConst || preg_match('/__bitwiseConst/', $prop->getDocComment())) {
                    $xml .= "\n" . <<<EOT
    {$setDoc}
    public function set{$function}BitwiseAdd({$returnType} \$$prop->name)
    {
        \$this->{$prop->name} = \$this->{$prop->name} | \$$prop->name;
        {$returnThis2}
    }

    /**
     * @param {$returnTypeSet} \${$prop->name}
     * @return boolean
     */
    public function get{$function}BitwiseHas({$returnType} \$$prop->name)
    {
        return boolval(\$this->{$prop->name} & \$$prop->name);
    }

    {$setDoc}
    public function set{$function}BitwiseFlip({$returnType} \$$prop->name)
    {
        \$this->{$prop->name} = \$this->{$prop->name} ^ \$$prop->name;
        {$returnThis2}
    }

    {$setDoc}
    public function set{$function}BitwiseRemove({$returnType} \$$prop->name)
    {
        \$this->{$prop->name} = \$this->{$prop->name} & (~\$$prop->name);
        {$returnThis2}
    }
EOT;

                }

                // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -
                // -- bitwiseConst -- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

                if ($auroraAnnotation->bitwiseConst || preg_match('/__bitwiseConst/', $prop->getDocComment())) {
                    $bitwiseConstIndex = 0;
                    foreach ($constants as $constantName => $constantValue) {
                        // If constant name start with annotation value
                        if (strpos($constantName, $auroraAnnotation->bitwiseConst) === 0) {

                            $annotationConstat    = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($auroraAnnotation->bitwiseConst)))); // STATUS_ => Status
                            $constantNameShort    = preg_replace("/^{$auroraAnnotation->bitwiseConst}/", '', $constantName);
                            $constantNameShort    = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($constantNameShort))));
                            $constantNameFunction = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($constantName)))); // dash to CamelCase

                            if (true || $bitwiseConstIndex > 0) {
                                $xml .= "\n";
                            }

                            $xml .= "\n" . <<<EOT
    /**
     * @param bool \$boolean
     * @return {$reflect->getShortName()}
     */
    public function set{$function}Has{$constantNameShort}(bool \$boolean)
    {
        \$boolean ? \$this->{$prop->name} |= self::{$constantName} : \$this->{$prop->name} &= ~self::{$constantName};
        {$returnThis2}
    }

    /**
     * @return bool
     */
    public function get{$function}Has{$constantNameShort}(): bool
    {
        return \$this->{$prop->name} & self::{$constantName};
    }
EOT;

                            ++$bitwiseConstIndex;
                        }
                    }

                    $xml .= "\n" . <<<EOT

    /**
     * Return a list of all '{$auroraAnnotation->bitwiseConst}*' constants
     * Primary useful for UIX, to display all possible constants
     *
     * @return array
     */
    public function get{$function}ListAll(): array
    {
        \$all{$function}Constants = [];

        \$oClass = new \ReflectionClass(__CLASS__);
        foreach (\$oClass->getConstants() as \$constant => \$constantValue) {
            if (0 === strpos(\$constant, '{$auroraAnnotation->bitwiseConst}')) {
                \$all{$function}Constants[(string)\$constant] = \$constantValue;
            }
        }
        return \$all{$function}Constants;
    }

    /**
     * Return a list of '{$auroraAnnotation->bitwiseConst}*' constants that are saved intro DB for current DB entry
     *
     * @return ?array
     */
    public function get{$function}ListSaved(): ?array
    {
        \$allSaved{$function}Constants = [];

        \$oClass = new \ReflectionClass(__CLASS__);
        foreach (\$oClass->getConstants() as \$constant => \$constantValue) {
            if (0 === strpos(\$constant, '{$auroraAnnotation->bitwiseConst}') && \$this->{$prop->name} & \$constantValue) {
                \$allSaved{$function}Constants[(string)\$constant] = \$constantValue;
            }
        }
        return \$allSaved{$function}Constants;
    }
EOT;
                }

                // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -
                // -- json -- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  - -

                if ($auroraAnnotation->json) {
                    $xml .= "\n" . <<<EOT
    {$setDoc}
    public function set{$function}Append({$returnType} \$$prop->name): {$reflect->getShortName()}
    {
        \$this->{$prop->name} = (is_array(\$this->$prop->name) ? array_merge(\$this->$prop->name, \$$prop->name) : \$$prop->name);
        {$returnThis2}
    }
EOT;
                    // TODO: add remove()
                }

                // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -
                // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -

            }

            $xml .= $toString;

            if ($overwrite) {

                //\preg_match_all('/private \$(.*);/i', $fileContent, $matches);
                //preg_match("/(?s)private(?!.*private).+;/", $fileContent, $matches);
                preg_match_all('/private[\s\??\w]*\s\$(.*);/', $fileContent, $matches);
                if (empty($matches[0]) && empty($matches[1])) {
                    preg_match_all('/protected[\s\??\w]*\s\$(.*);/', $fileContent, $matches);
                }

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
use Doctrine\Persistence\ManagerRegistry;

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