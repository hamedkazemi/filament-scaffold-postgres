<?php

namespace Solutionforest\FilamentScaffold\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Solutionforest\FilamentScaffold\Resources\ScaffoldResource\Pages;

if (! defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

class ScaffoldResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    /********************************************
     * Group name in the 'navigation bar'
     * @var string|null
     */
    protected static ?string $navigationGroup = 'System';

    /********************************************
     * Plural label for the resource
     * @var string|null
     */
    protected static ?string $pluralModelLabel = 'Scaffold';

    protected static ?string $navigationLabel = 'Scaffold Manager';

    /********************************************
     * Singular label for the resource
     * @var string|null
     */
    protected static ?string $modelLabel = 'Scaffold';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                /********************************************
                 * TABLE NAME, MODEL NAME, RESOURCE NAME
                 */
                Forms\Components\Section::make('Table & Resource Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([

                                Forms\Components\TextInput::make('Table Name')
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $modelName = str_replace('_', '', ucwords($state, '_'));
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', 'app\\Filament\\Resources\\' . $modelName . 'Resource');
                                        $set('Choose Table', $state);
                                    })
                                    ->required(),

                                Forms\Components\Select::make('Choose Table')
                                    ->options(self::getAllTableNames())
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $allTables = self::getAllTableNames();

                                        if (! isset($allTables[$state])) {
                                            return;
                                        }

                                        $tableName = $allTables[$state];
                                        $tableColumns = self::getTableColumns($tableName);
                                        $modelName = str_replace('_', '', ucwords($tableName, '_'));
                                        $set('Table Name', $tableName);
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', 'app\\Filament\\Resources\\' . $modelName . 'Resource');
                                        $set('Table', $tableColumns);
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('Model')
                                    ->default('app\\Models\\')
                                    ->live(onBlur: true),
                                Forms\Components\TextInput::make('Resource')
                                    ->default('app\\Filament\\Resources\\')
                                    ->live(onBlur: true),
                            ]),
                    ])
                    ->columnSpan(2),

                /********************************************
                 * GENERATION OPTIONS
                 */
                Forms\Components\Section::make('Generation Options')
                    ->schema([
                        Forms\Components\Checkbox::make('Create Resource')
                            ->default(true),
                        Forms\Components\Checkbox::make('Create Model')
                            ->default(true),
                        Forms\Components\Checkbox::make('Simple Resource')
                            ->default(false)
                            ->label('Simple (Modal Type) Resource'),
                        Forms\Components\Checkbox::make('Create Migration'),
                        Forms\Components\Checkbox::make('Create Factory'),
                        Forms\Components\Checkbox::make('Create Controller'),
                        Forms\Components\Checkbox::make('Run Migrate'),
                        Forms\Components\Checkbox::make('Create Route'),
                        Forms\Components\Checkbox::make('Create Policy')
                            ->default(false)
                            ->hidden(fn () => ! class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)),
                    ])
                    ->columns(2)
                    ->columnSpan(1),

                /********************************************
                 * TABLE STRUCTURE
                 */
                Forms\Components\Section::make('Table Structure')
                    ->schema([
                        Forms\Components\Repeater::make('Table')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Field Name')
                                    ->required()
                                    ->default(fn ($record) => $record['name'] ?? ''),
                                Forms\Components\TextInput::make('translation'),
                                Forms\Components\Select::make('type')
                                    ->native(false)
                                    ->searchable()
                                    ->options([
                                        'string' => 'string',
                                        'integer' => 'integer',
                                        'bigInteger' => 'bigInteger',
                                        'text' => 'text',
                                        'float' => 'float',
                                        'double' => 'double',
                                        'decimal' => 'decimal',
                                        'boolean' => 'boolean',
                                        'date' => 'date',
                                        'time' => 'time',
                                        'datetime' => 'dateTime',
                                        'timestamp' => 'timestamp',
                                        'char' => 'char',
                                        'mediumText' => 'mediumText',
                                        'longText' => 'longText',
                                        'tinyInteger' => 'tinyInteger',
                                        'smallInteger' => 'smallInteger',
                                        'mediumInteger' => 'mediumInteger',
                                        'json' => 'json',
                                        'jsonb' => 'jsonb',
                                        'binary' => 'binary',
                                        'enum' => 'enum',
                                        'ipAddress' => 'ipAddress',
                                        'macAddress' => 'macAddress',
                                    ])
                                    ->default(fn ($record) => $record['type'] ?? 'string')
                                    ->reactive(),
                                Forms\Components\Checkbox::make('nullable')
                                    ->inline(false)
                                    ->default(fn ($record) => $record['nullable'] ?? false),
                                Forms\Components\Select::make('key')
                                    ->default('')
                                    ->options([
                                        '' => 'NULL',
                                        'primary' => 'Primary',
                                        'unique' => 'Unique',
                                        'index' => 'Index',
                                    ])
                                    ->default(fn ($record) => $record['key'] ?? ''),
                                Forms\Components\TextInput::make('default')
                                    ->default(fn ($record) => $record['default'] ?? ''),
                                Forms\Components\Textarea::make('comment')
                                    ->autosize()
                                    ->default(fn ($record) => $record['comment'] ?? ''),
                                Forms\Components\Repeater::make('foreign_keys')
                                    ->label('Foreign Keys')
                                    ->schema([
                                        Forms\Components\Select::make('foreign_table')
                                            ->label('Foreign Table')
                                            ->options(self::getAllTableNames())
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $allTables = self::getAllTableNames();
                                                if ($allTables[$state]) {
                                                    $tableColumns = self::getTableColumns($allTables[$state]);
                                                    $columnNames = array_column($tableColumns, 'name', 'name');
                                                    $set('foreign_key_options', $columnNames);
                                                } else {
                                                    $set('foreign_key_options', []);
                                                }
                                            }),
                                        Forms\Components\Select::make('foreign_key')
                                            ->label('Foreign Key')
                                            ->options(function (callable $get) {
                                                return $get('foreign_key_options') ?? [];
                                            })
                                            ->reactive(),
                                    ])
                                    ->columns(1),
                            ])
                            ->columns(1),
                    ])
                    ->columnSpan('full'),

                /********************************************
                 * MIGRATION ADDITIONAL FEATURES
                 */
                Forms\Components\Section::make('Migration Additional Features')
                    ->schema([
                        Forms\Components\Checkbox::make('Created_at & Updated_at')
                            ->label('Created_at & Updated_at timestamps')
                            ->default(true)
                            ->inline(),
                        Forms\Components\Checkbox::make('Soft Delete')
                            ->label('Soft Delete (recycle bin)')
                            ->default(true)
                            ->inline(),
                    ])
                    ->columns(2)
                    ->columnSpan('full'),
            ])
            ->columns(3);
    }


    public static function getAllTableNames(): array
    {
        $tables = DB::select("SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'");

        return array_map('current', $tables);
    }

    public static function getTableColumns($tableName)
    {
        $columns = DB::select("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = '{$tableName}' AND table_schema = 'public';");

        $columnDetails = [];

        $typeMapping = [
            'character varying' => 'string',
            'varchar' => 'string',
            'character' => 'char',
            'char' => 'char',
            'text' => 'text',
            'citext' => 'text',
            'name' => 'string',

            'integer' => 'integer',
            'int' => 'integer',
            'int4' => 'integer',
            'smallint' => 'smallInteger',
            'int2' => 'smallInteger',
            'bigint' => 'bigInteger',
            'int8' => 'bigInteger',
            'serial' => 'integer', // typically auto-incrementing
            'serial4' => 'integer',
            'smallserial' => 'smallInteger',
            'serial2' => 'smallInteger',
            'bigserial' => 'bigInteger',
            'serial8' => 'bigInteger',

            'numeric' => 'decimal',
            'decimal' => 'decimal',
            'real' => 'float',
            'float4' => 'float',
            'double precision' => 'double',
            'float8' => 'double',
            'money' => 'decimal',

            'boolean' => 'boolean',
            'bool' => 'boolean',

            'date' => 'date',
            'time without time zone' => 'time',
            'time with time zone' => 'timeTz',
            'timetz' => 'timeTz',
            'timestamp without time zone' => 'timestamp',
            'timestamp with time zone' => 'timestampTz',
            'timestamptz' => 'timestampTz',
            'interval' => 'interval',

            'json' => 'json',
            'jsonb' => 'jsonb',

            'bytea' => 'binary',
            'blob' => 'binary',
            'byte' => 'binary',

            'uuid' => 'uuid',

            'array' => 'array', // PostgreSQL supports array types
            'bit' => 'bit',
            'varbit' => 'bit varying',

            'cidr' => 'cidr',
            'inet' => 'inet',
            'macaddr' => 'macAddress',
            'macaddr8' => 'macAddress',

            'tsvector' => 'tsvector',
            'tsquery' => 'tsquery',

            'xml' => 'xml',

            'box' => 'geometry',
            'circle' => 'geometry',
            'line' => 'geometry',
            'lseg' => 'geometry',
            'path' => 'geometry',
            'point' => 'geometry',
            'polygon' => 'geometry',

            'geometry' => 'geometry', // PostGIS extension
            'geography' => 'geography', // PostGIS extension
            'hstore' => 'hstore',

            'enum' => 'enum',
            'range' => 'range',

            // Custom mappings as needed
        ];

        $keyMapping = [
            'PRI' => 'primary',
            'UNI' => 'unique',
            'MUL' => 'index',
        ];

        foreach ($columns as $column) {
            // Use column_name instead of Field to refer to the column name
            if ($column->column_name === 'id' ||
                $column->column_name === 'ID' ||
                $column->column_name === 'created_at' ||
                $column->column_name === 'updated_at' ||
                $column->column_name === 'deleted_at') {
                continue;
            }

            $type = preg_replace('/\(.+\)/', '', $column->data_type);
            $type = preg_split('/\s+/', $type)[0];

            // PostgreSQL doesn't have a direct Key property like MySQL, so you might need to handle keys differently or skip this part
            $key = ''; // Adjust this part depending on how you determine keys in your PostgreSQL schema

            $translatedType = $typeMapping[$type] ?? $type;
            $translatedKey = $keyMapping[$key] ?? $key;

            $columnDetails[] = [
                'name' => $column->column_name,
                'type' => $translatedType,
                'nullable' => $column->is_nullable === 'YES',
                'key' => $translatedKey,
                'default' => $column->column_default,
                'comment' => '',
            ];
        }

        return $columnDetails;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateScaffold::route('/'),
        ];
    }

    public static function getFileName($path)
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $fileNameWithExtension = basename($normalizedPath);
        $fileName = pathinfo($fileNameWithExtension, PATHINFO_FILENAME);

        return $fileName;
    }

    public static function generateFiles(array $data)
    {
        $basePath = base_path();

        $modelName = self::getFileName($data['Model']);

        $resourceName = self::getFileName($data['Resource']);

        chdir($basePath);
        $migrationPath = null;
        $resourcePath = null;
        $modelPath = null;
        $controllerPath = null;

        /********************************************
         * MIGRATION FILE
         */
        if ($data['Create Migration']) {
            Artisan::call('make:migration', [
                'name' => 'create_' . $data['Table Name'] . '_table',
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            if (strpos($output, 'Migration') !== false) {
                preg_match('/\[([^\]]+)\]/', $output, $matches);
                $migrationPath = $matches[1] ?? null;
            }
            self::overwriteMigrationFile($migrationPath, $data);
        }

        if ($data['Create Factory']) {
            Artisan::call('make:factory', [
                'name' => $data['Table Name'] . 'Factory',
                '--no-interaction' => true,
            ]);
        }

        /********************************************
         * CREATE MODEL
         */
        if ($data['Create Model']) {
            Artisan::call('make:model', [
                'name' => $modelName,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            if (strpos($output, 'Model') !== false) {
                preg_match('/\[([^\]]+)\]/', $output, $matches);
                $modelPath = $matches[1] ?? null;
            }
            self::overwriteModelFile($modelPath, $data);
        }

        /********************************************
         * CREATE RESOURCE FILE
         */
        if ($data['Create Resource']) {
            $command = [
                'name' => $resourceName,
                '--generate' => true,
                '--view' => true,
                '--force' => true,
                '--no-interaction' => true,
            ];

            /**************************
             * --simple (modal type)
             */
            if ($data['Simple Resource']) {
                $command['--simple'] = true;
            }

            Artisan::call('make:filament-resource', $command);
            $output = Artisan::output();
            preg_match('/\[([^\]]+)\]/', $output, $matches);
            $resourcePath = $matches[1] ?? null;
            self::overwriteResourceFile($resourcePath, $data);
        }

        /********************************************
         * CREATE CONTROLLER
         */
        if ($data['Create Controller']) {
            Artisan::call('make:controller', [
                'name' => $data['Table Name'] . 'Controller',
                '--model' => $modelName,
                '--resource' => true,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            preg_match('/\[([^\]]+)\]/', $output, $matches);
            $controllerPath = $matches[1] ?? null;
            self::overwriteControllerFile($controllerPath, $data);
        }

        /********************************************
         * POLICY FILE (For Permissions)
         */
        if (class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            /** @phpstan-ignore-next-line */
            $url = \BezhanSalleh\FilamentShield\Resources\RoleResource::getUrl();
            if ($data['Create Policy']) {
                $modelName = self::getFileName($data['Model']);
                Artisan::call('make:policy', [
                    'name' => $modelName . 'Policy',
                    '--model' => $modelName,
                    '--no-interaction' => true,
                ]);
                $output = Artisan::output();
                if (strpos($output, 'Policy') !== false) {
                    preg_match('/\[([^\]]+)\]/', $output, $matches);
                    $policyPath = $matches[1] ?? null;
                    if ($policyPath) {
                        self::updatePolicyFile($policyPath, $modelName);
                        // Log::info("Policy file created and updated at: $policyPath");
                        /********************************************
                         * SUCCESS NOTIFICATION
                         */
                        Notification::make()
                            ->success()
                            ->persistent()
                            ->title('Scaffold with Policy Created Successfully!')
                            ->body('A new policy file has been successfully created for your model. Please configure the permissions for the new policy.')
                            ->icon('heroicon-o-shield-check')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view')
                                    ->label('Configure Permissions')
                                    ->button()
                                    ->url($url, shouldOpenInNewTab: true),
                                \Filament\Notifications\Actions\Action::make('close')
                                    ->color('gray')
                                    ->close(),
                            ])
                            ->send();
                    }
                }
            }
        } else {
            $url = '/default-url';
        }

        /********************************************
         * EXECUTE THE CREATING OF ROUTE
         * IF Create Route is Check
         */
        if ($data['Create Route']) {
            $controllerName = self::getFileName($controllerPath);
            self::addRoutes($data, $controllerName);
        }

        /********************************************
         * AFTER FILE/DB GENERATION, RUN THIS ARTISAN COMMANDS:
         */
        $commands = [
            'cache:clear',
            'config:cache',
            'config:clear',
            'route:cache',
            'route:clear',
            'icons:cache',
            'filament:cache-components',
        ];

        $commandErrors = [];

        foreach ($commands as $command) {
            $fullCommand = "php artisan $command";
            $descriptorspec = [
                0 => ['pipe', 'r'], //stdin
                1 => ['pipe', 'w'], //stdout
                2 => ['pipe', 'w'],  //stderr
            ];

            $process = proc_open($fullCommand, $descriptorspec, $pipes, base_path());

            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $return_value = proc_close($process);

                if ($return_value !== 0) {
                    Log::error("Error running artisan command: $fullCommand", [
                        'error' => $error,
                        'output' => $output,
                    ]);
                    $commandErrors[] = $fullCommand;
                }
            }
        }

        if (empty($commandErrors)) {

            /********************************************
             * SUCCESS NOTIFICATION
             */
            // $resourceClickLink = "\\App\\Filament\\Resources\\" . $resourceName;
            Notification::make()
                ->success()
                ->persistent()
                ->title('Scaffold created')
                ->body('The scaffold resource has been created successfully.')
                ->icon('heroicon-o-cube-transparent')
                // ->actions([
                //     \Filament\Notifications\Actions\Action::make('view')
                //         ->button()
                //         ->url(class_exists($resourceClickLink) ? $resourceClickLink::getUrl() : '#', shouldOpenInNewTab: true),
                //     \Filament\Notifications\Actions\Action::make('close')
                //         ->color('gray')
                //         ->close(),
                // ])
                ->send();
        } else {
            /********************************************
             * ERROR
             */
            Notification::make()
                ->title('Error running commands')
                ->body('Check logs for more details.')
                ->danger()
                ->send();
        }

    }

    public static function overwriteResourceFile($resourceFile, $data)
    {
        $modelName = self::getFileName($data['Model']);

        if (file_exists($resourceFile)) {
            $content = file_get_contents($resourceFile);

            $formSchema = self::generateFormSchema($data);
            $tableSchema = self::generateTableSchema($data);
            $useClassChange = <<<EOD
                use App\\Models\\$modelName;
                EOD;

            $classChange = <<<EOD
                protected static ?string \$model = $modelName::class;
                EOD;

            $formFunction = <<<EOD
                public static function form(Form \$form): Form
                    {
                        return \$form
                            ->schema([
                                $formSchema
                            ]);
                    }
                EOD;

            $tableFunction = <<<EOD
                public static function table(Table \$table): Table
                    {
                        return \$table
                            ->columns([
                                $tableSchema
                            ])
                            ->filters([
                                //
                            ])
                            ->actions([
                                Tables\Actions\ViewAction::make(),
                                Tables\Actions\EditAction::make(),
                            ])
                            ->bulkActions([
                                Tables\Actions\BulkActionGroup::make([
                                    Tables\Actions\DeleteBulkAction::make(),
                                ]),
                            ]);
                    }
                EOD;

            $content = preg_replace('/use\s+App\\\\Models\\\\.*?;/s', $useClassChange, $content);
            $content = preg_replace('/protected static\s+\?string\s+\$model\s*=\s*[^\;]+;/s', $classChange, $content);
            $content = preg_replace('/public static function form.*?{.*?}/s', $formFunction, $content);
            $content = preg_replace('/public static function table.*?{.*?}/s', $tableFunction, $content);

            file_put_contents($resourceFile, $content);
        }
    }

    public static function generateFormSchema($data)
    {
        $fields = [];
        foreach ($data['Table'] as $column) {
            $fields[] = "Forms\Components\TextInput::make('{$column['name']}')->required()";
        }

        return implode(",\n", $fields);
    }

    public static function generateTableSchema($data)
    {
        $columns = [];
        foreach ($data['Table'] as $column) {
            $columns[] = "Tables\Columns\TextColumn::make('{$column['name']}')->sortable()->searchable()";
        }

        return implode(",\n", $columns);
    }

    public static function overwriteMigrationFile($filePath, $data)
    {
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);

            $upPart = self::generateUp($data);
            $upFunction = <<<EOD
            public function up(): void
                {
                    Schema::create('{$data['Table Name']}', function (Blueprint \$table) {
                        \$table->id();
                        $upPart;
                        \$table->timestamps();
                        if ({$data['Soft Delete']}) {
                            \$table->softDeletes();
                        }
                    });
                }
            EOD;

            $downFunction = <<<EOD
            public function down(): void
                {
                    Schema::dropIfExists('{$data['Table Name']}');
                }
            EOD;

            $content = preg_replace('/public function up.*?{.*?}/s', $upFunction, $content);
            $content = preg_replace('/public function down.*?{.*?}/s', $downFunction, $content);

            file_put_contents($filePath, $content);
        }
    }

    public static function generateUp(array $data): string
    {
        $fields = array_map(
            fn (array $column): string => self::generateColumnDefinition($column),
            $data['Table']
        );

        // Append the foreign keys if any
        foreach ($data['Table'] as $column) {
            if (!empty($column['foreign_table']) && !empty($column['foreign_key'])) {
                $fields[] = "\$table->foreignId('{$column['name']}')->constrained('{$column['foreign_table']}')->onDelete('cascade');";
            }
        }

        return implode(";\n", $fields);
    }


    private static function generateColumnDefinition(array $column): string
    {
        $definition = "\$table->{$column['type']}('{$column['name']}')";

        if ($column['nullable']) {
            $definition .= "->nullable()";
        }

        if ($column['default'] !== null) {
            $definition .= "->default('{$column['default']}')";
        }

        if (!empty($column['key']) && $column['key'] !== 'NULL') {
            $definition .= "->{$column['key']}()";
        }

        if (!empty($column['comment'])) {
            $definition .= "->comment('{$column['comment']}')";
        }

        return $definition;
    }

    public static function overwriteModelFile($filePath, $data)
    {
        $columnNames = self::getColumn($data);

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $useSoftDel = <<<EOD
            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\SoftDeletes;
            EOD;

            $chooseTable = <<<EOD
            use HasFactory;
                protected \$table = '{$data['Table Name']}';
                protected \$fillable = $columnNames;
            EOD;

            $withSoftdel = <<<EOD
            use HasFactory;
                use SoftDeletes;
                protected \$table = '{$data['Table Name']}';
                protected \$fillable = $columnNames;
            EOD;

            if ($data['Soft Delete'] == true) {
                $content = preg_replace('/use Illuminate\\\\Database\\\\Eloquent\\\\Model;/s', $useSoftDel, $content);
                $content = preg_replace('/use HasFactory;/s', $withSoftdel, $content);
            } else {
                $content = preg_replace('/use HasFactory;/s', $chooseTable, $content);
            }

            // Add relationships
            $relationships = self::generateRelationships($data);
            $content = preg_replace('/class\s+[^\s]+\s+extends\s+Model\s*{/', "class " . self::getFileName($data['Model']) . " extends Model {\n\n$relationships", $content);

            file_put_contents($filePath, $content);
        }
    }

    private static function generateRelationships(array $data): string
    {
        $relationships = [];

        foreach ($data['Table'] as $column) {
            if (!empty($column['foreign_table']) && !empty($column['foreign_key'])) {
                $relatedModel = ucfirst(Str::camel(Str::singular($column['foreign_table'])));
                $relationships[] = <<<EOD
                public function {$relatedModel}()
                {
                    return \$this->belongsTo({$relatedModel}::class);
                }
            EOD;
            }
        }

        return implode("\n\n", $relationships);
    }


    public static function getColumn($data)
    {
        $fields = [];
        foreach ($data['Table'] as $column) {
            $fields[] = "{$column['name']}";
        }

        return "['" . implode("','", $fields) . "']";
    }

    public static function overwriteControllerFile($filePath, $data)
    {
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $changeIndex = <<<'EOD'
                public function index()
                    {
                        return 'This your index page';
                    }
                EOD;

            $content = preg_replace('/public function index.*?{.*?}/s', $changeIndex, $content);
            file_put_contents($filePath, $content);
        }

    }

    /********************************************
     * GENERATE ROUTE, IF ALLOWED
     */
    public static function addRoutes($data, $controllerName)
    {
        $filePath = base_path('routes\web.php');
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $useStatement = <<<EOD
                use Illuminate\Support\Facades\Route;
                use App\Http\Controllers\\$controllerName;
                EOD;

            $addRoute = <<<EOD

                Route::resource('{$data['Table Name']}', {$controllerName}::class)->only([
                    'index', 'show'
                ]);
                EOD;

            $content = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/s', $useStatement, $content);
            $content .= $addRoute;

            file_put_contents($filePath, $content);
        }
    }

    /********************************************
     * CREATE POLICY FILE, IF THERE'S A FilamentShield
     */
    public static function updatePolicyFile($filePath, $modelName)
    {

        // --- Check if FilamentShield is installed
        if (! class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            return;
        }

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);

            $modelFunctionNameVariable = Str::snake(Str::plural($modelName));
            $permissionBase = Str::of($modelName)
                ->afterLast('\\')
                ->snake()
                ->replace('_', '::');

            $methodTemplates = [
                'import_data' => "return \$user->can('import_data_{$permissionBase}');",
                'download_template_file' => "return \$user->can('download_template_file_{$permissionBase}');",
                'viewAny' => "return \$user->can('view_any_{$permissionBase}');",
                'view' => "return \$user->can('view_{$permissionBase}');",
                'create' => "return \$user->can('create_{$permissionBase}');",
                'update' => "return \$user->can('update_{$permissionBase}');",
                'delete' => "return \$user->can('delete_{$permissionBase}');",
                'deleteAny' => "return \$user->can('delete_any_{$permissionBase}');",
                'restore' => "return \$user->can('restore_{$permissionBase}');",
                'restoreAny' => "return \$user->can('restore_any_{$permissionBase}');",
                'forceDelete' => "return \$user->can('force_delete_{$permissionBase}');",
                'forceDeleteAny' => "return \$user->can('force_delete_any_{$permissionBase}');",
                'replicate' => "return \$user->can('replicate_{$permissionBase}');",
                'reorder' => "return \$user->can('reorder_{$permissionBase}');",
            ];

            $newMethods = '';
            foreach ($methodTemplates as $method => $returnStatement) {
                $methodSignature = "public function {$method}(User \$user" .
                    (
                        in_array($method, ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny', 'reorder', 'import_data', 'download_template_file'])
                        ? ''
                        : ", {$modelName} \${$modelFunctionNameVariable}"
                    ) .
                    '): bool';

                $methodBody = "    {\n        {$returnStatement}\n    }";

                $fullMethod = "\n\n    {$methodSignature}\n{$methodBody}";

                // --- Check if the method already exists
                if (strpos($content, "public function {$method}(") === false) {
                    // Method doesn't exist, add it to newMethods
                    $newMethods .= $fullMethod;
                } else {
                    // --- Method exists, update it
                    $pattern = "/public function {$method}\([^\)]*\): bool\n\s*{\n.*?\n\s*}/s";
                    $replacement = "{$methodSignature}\n{$methodBody}";
                    $content = preg_replace($pattern, $replacement, $content);
                }
            }

            // --- Add new methods inside the class
            $content = preg_replace('/}(\s*)$/', $newMethods . "\n}", $content);

            file_put_contents($filePath, $content);
        }
    }
}
