<?php
declare(strict_types=1);

function course_catalog_seed_data(): array
{
    $beginner = 'Beginner';
    $intermediate = 'Intermediate';
    $courses = [
        [
            'Python Programming', 'Programming Languages',
            'Build a practical foundation in Python syntax, data structures, functions, and common language features.',
            20, $beginner, [
                [$beginner, 'Which statement best describes Python?', ['A low-level assembly language', 'A high-level general-purpose programming language', 'A database query language', 'A markup language'], 1],
                [$beginner, 'Which built-in function returns the number of items in a list?', ['count()', 'size()', 'len()', 'items()'], 2],
                [$beginner, 'Which Python collection is immutable?', ['list', 'set', 'dictionary', 'tuple'], 3],
                [$beginner, 'What does a Python dictionary store?', ['Ordered characters only', 'Key-value pairs', 'Only unique numbers', 'A sequence of source files'], 1],
                [$beginner, 'How does Python normally mark the body of an if statement?', ['With indentation', 'With begin and end keywords', 'With square brackets', 'With a trailing colon only'], 0],
                [$intermediate, 'Which operator tests whether two values are equal?', ['=', '==', ':=', '=>'], 1],
                [$intermediate, 'Which expression creates a list of squares for every value in nums?', ['[x * x for x in nums]', '{x * x in nums}', 'list(x * x, nums)', '(x * x for nums)'], 0],
                [$intermediate, 'Why is a file often opened with a with statement?', ['It automatically closes the file when the block exits', 'It makes the file writable on every system', 'It disables file errors', 'It converts the file to JSON'], 0],
                [$intermediate, 'What is a key property of a generator that uses yield?', ['It evaluates all results immediately', 'It produces values lazily as they are requested', 'It can only return integers', 'It automatically sorts its values'], 1],
                [$intermediate, 'What does the is operator primarily compare?', ['Whether two values have equal contents', 'Whether two references identify the same object', 'Whether two strings have the same length', 'Whether two numbers have the same type'], 1],
            ],
        ],
        [
            'Java Programming', 'Programming Languages',
            'Learn Java fundamentals including types, arrays, object behavior, exceptions, and the JVM ecosystem.',
            20, $beginner, [
                [$beginner, 'What does the Java compiler normally produce for execution by the JVM?', ['Bytecode', 'HTML', 'SQL', 'Machine code for one specific CPU only'], 0],
                [$beginner, 'Which keyword declares a class in Java?', ['object', 'define', 'class', 'struct'], 2],
                [$beginner, 'What is the first valid index of a Java array?', ['-1', '0', '1', 'The array length'], 1],
                [$beginner, 'Which Java type represents a true-or-false value?', ['bool', 'BooleanLiteral', 'boolean', 'bit'], 2],
                [$beginner, 'Which statement creates a new Scanner object?', ['Scanner input = new Scanner(System.in);', 'Scanner input = Scanner(System.in);', 'new Scanner input(System.in);', 'Scanner = new System.in();'], 0],
                [$intermediate, 'How does == generally compare two object variables in Java?', ['It compares their references', 'It always compares every field', 'It compares their hash codes only', 'It calls compareTo automatically'], 0],
                [$intermediate, 'What is true about Java String objects?', ['They are mutable by default', 'They are immutable', 'They cannot contain spaces', 'They are primitive types'], 1],
                [$intermediate, 'What does method overriding enable in an inheritance hierarchy?', ['A subclass implementation to replace an inherited method behavior', 'A method to have no return type', 'A class to inherit from multiple classes', 'A field to become local'], 0],
                [$intermediate, 'What is the usual purpose of a finally block?', ['To run cleanup code after try/catch processing', 'To declare a checked exception', 'To restart the JVM', 'To prevent all exceptions'], 0],
                [$intermediate, 'What is the average lookup complexity of a well-distributed HashMap key?', ['O(1)', 'O(n log n)', 'O(n^2)', 'O(2^n)'], 0],
            ],
        ],
        [
            'C Programming', 'Programming Languages',
            'Practice C syntax, functions, arrays, pointers, structures, and responsible manual memory management.',
            20, $beginner, [
                [$beginner, 'Which header declares the standard printf function?', ['<stdlib.h>', '<stdio.h>', '<string.h>', '<math.h>'], 1],
                [$beginner, 'Which function signature is a conventional program entry point in C?', ['int main(void)', 'void start()', 'begin(int)', 'program main(void)'], 0],
                [$beginner, 'What does sizeof expression return?', ['The number of bits in the expression', 'The number of bytes occupied by the expression type', 'The expression value rounded up', 'The address of the expression'], 1],
                [$beginner, 'What does a pointer variable store?', ['A memory address', 'A function result only', 'An array length', 'A preprocessor directive'], 0],
                [$beginner, 'What is the first index of a C array?', ['-1', '0', '1', 'It depends on the compiler'], 1],
                [$intermediate, 'Which header declares malloc and free?', ['<stdio.h>', '<ctype.h>', '<stdlib.h>', '<limits.h>'], 2],
                [$intermediate, 'What character terminates a C string?', ['A space', 'A newline', 'The null character \\0', 'The character 0'], 2],
                [$intermediate, 'What does const int *p prevent through p?', ['Changing the pointed-to int', 'Changing p to point elsewhere', 'Reading the pointed-to int', 'Allocating memory'], 0],
                [$intermediate, 'What is a C structure primarily used for?', ['Grouping related members that may have different types', 'Creating a loop', 'Declaring a macro', 'Allocating an array automatically'], 0],
                [$intermediate, 'What should a program do after it no longer needs memory returned by malloc?', ['Call free on the allocated pointer', 'Set every byte to zero only', 'Call printf on the pointer', 'Use realloc with size zero on every platform'], 0],
            ],
        ],
        [
            'C++ Programming', 'Programming Languages',
            'Explore modern C++ types, standard containers, object lifetime, templates, and compile-time and runtime polymorphism.',
            25, $intermediate, [
                [$beginner, 'Which standard container is a dynamically resizable array?', ['std::vector', 'std::stackframe', 'std::record', 'std::bufferlist'], 0],
                [$beginner, 'Which header is commonly needed for std::string?', ['<iostream>', '<string>', '<vector>', '<memory>'], 1],
                [$beginner, 'What is a constructor primarily used for?', ['Initializing a newly created object', 'Destroying a program', 'Sorting a container', 'Declaring a namespace'], 0],
                [$beginner, 'What does the scope resolution operator :: commonly separate?', ['A namespace or class name from a member name', 'Two array indexes', 'A condition from its body', 'A pointer from its value'], 0],
                [$beginner, 'Which keyword declares a constant object or value?', ['fixed', 'constant', 'const', 'readonly'], 2],
                [$intermediate, 'What is the main ownership guarantee of std::unique_ptr?', ['It has one owning smart-pointer instance at a time', 'It shares ownership with every pointer', 'It never releases memory', 'It only owns arrays of characters'], 0],
                [$intermediate, 'What does declaring a virtual function support?', ['Runtime dispatch through a base-class interface', 'Compile-time macro expansion', 'Automatic serialization', 'Multiple inheritance of constructors'], 0],
                [$intermediate, 'What is RAII in C++?', ['Managing resource lifetime through object lifetime', 'A runtime interpreter for templates', 'A required array format', 'A networking protocol'], 0],
                [$intermediate, 'What does std::move generally enable?', ['Treating an expression as eligible for move operations', 'Moving an object to another thread automatically', 'Copying every member twice', 'Changing a variable name'], 0],
                [$intermediate, 'What is a function template useful for?', ['Writing type-generic code instantiated for suitable types', 'Executing a function in parallel by default', 'Loading a shared library', 'Replacing all class constructors'], 0],
            ],
        ],
        [
            'Object-Oriented Programming', 'Programming Languages',
            'Understand object-oriented design through encapsulation, abstraction, inheritance, polymorphism, and composition.',
            20, $intermediate, [
                [$beginner, 'Which OOP principle bundles data with methods and controls access to internal state?', ['Encapsulation', 'Compilation', 'Iteration', 'Normalization'], 0],
                [$beginner, 'Which principle exposes essential behavior while hiding implementation details?', ['Abstraction', 'Recursion', 'Serialization', 'Indexing'], 0],
                [$beginner, 'What relationship does inheritance commonly represent?', ['An is-a relationship', 'A file-to-folder relationship only', 'A network connection', 'A database transaction'], 0],
                [$beginner, 'What is an object?', ['A runtime instance of a class', 'A type of loop', 'A compiler warning', 'A database index'], 0],
                [$beginner, 'What does a constructor usually do?', ['Establishes an object’s initial state', 'Deletes the class definition', 'Runs only after the program exits', 'Converts an object to a database table'], 0],
                [$intermediate, 'What does polymorphism allow in a well-designed OOP system?', ['Different implementations to be used through a common interface', 'Every class to have identical fields', 'Objects to bypass type checking', 'Methods to run without being called'], 0],
                [$intermediate, 'What is composition?', ['Building an object using other objects as parts', 'A class inheriting from itself', 'Converting a method into a variable', 'A way to prevent object creation'], 0],
                [$intermediate, 'Why can composition be preferable to deep inheritance hierarchies?', ['It can reduce coupling and make behavior easier to vary', 'It guarantees fewer objects in memory', 'It removes the need for interfaces', 'It prevents code reuse'], 0],
                [$intermediate, 'What does an interface primarily define?', ['A contract of operations a type can provide', 'The private memory layout of every object', 'A database schema migration', 'A user interface color palette'], 0],
                [$intermediate, 'What is dependency injection?', ['Providing a component’s dependencies from outside the component', 'Creating every dependency with global variables', 'Hiding all class methods', 'Copying a project into a constructor'], 0],
            ],
        ],
        [
            'Operating Systems', 'Computer Science Fundamentals',
            'Learn how operating systems manage processes, memory, files, synchronization, and hardware resources.',
            25, $intermediate, [
                [$beginner, 'What is a process?', ['A program in execution with its own operating-system-managed state', 'A source-code comment', 'A CPU instruction set', 'A database record'], 0],
                [$beginner, 'What is a thread?', ['A unit of execution within a process', 'A physical network cable', 'A file system partition', 'A type of compiler'], 0],
                [$beginner, 'What is the operating system scheduler responsible for?', ['Selecting which runnable task gets CPU time', 'Translating every source file into HTML', 'Assigning database primary keys', 'Encrypting every disk sector'], 0],
                [$beginner, 'What is virtual memory?', ['An abstraction that gives processes a virtual address space backed by physical memory and storage', 'A memory chip used only for graphics', 'A cache that stores browser history', 'A technique for increasing CPU clock speed'], 0],
                [$beginner, 'What does a system call allow a program to do?', ['Request a service from the operating-system kernel', 'Call a remote procedure without a network', 'Change its source code at runtime', 'Bypass all access controls'], 0],
                [$intermediate, 'Which condition is required for a deadlock to be possible?', ['Circular wait among processes', 'Every process always releases resources immediately', 'All resources are infinitely available', 'The CPU has only one register'], 0],
                [$intermediate, 'What is a mutex used for?', ['Providing mutual exclusion around shared state', 'Scheduling disk backups', 'Compressing process memory', 'Assigning IP addresses'], 0],
                [$intermediate, 'What is a context switch?', ['Saving one task’s execution state and restoring another’s', 'Changing the source-code language', 'Moving a file between folders', 'Restarting the computer after each instruction'], 0],
                [$intermediate, 'What does a counting semaphore represent?', ['A counter used to coordinate access to a limited number of resources', 'The number of CPUs installed', 'A virtual-memory page size', 'A measure of network latency'], 0],
                [$intermediate, 'Why does a page-replacement policy matter?', ['It chooses which memory page to evict when space is needed', 'It decides which user may log in', 'It formats source code', 'It selects a programming language'], 0],
            ],
        ],
        [
            'Software Engineering', 'Computer Science Fundamentals',
            'Practice the engineering lifecycle: requirements, design, testing, collaboration, delivery, and maintenance.',
            20, $beginner, [
                [$beginner, 'What is a software requirement?', ['A documented need or capability the system should satisfy', 'A source-code formatting rule only', 'A database backup file', 'A compiler output'], 0],
                [$beginner, 'What does version control help a team track?', ['Changes to files over time and collaboration history', 'Only the current CPU temperature', 'User passwords in plain text', 'The order of network packets'], 0],
                [$beginner, 'What does a unit test usually check?', ['A small, isolated unit of behavior', 'The entire production network under every possible load', 'Only the visual design of a website', 'Whether the source files have a license'], 0],
                [$beginner, 'What is a code review?', ['A structured peer examination of proposed code changes', 'An automated database backup', 'A production deployment environment', 'A substitute for all testing'], 0],
                [$beginner, 'What is software maintenance?', ['Updating software to fix, adapt, or improve it after release', 'Deleting every old test', 'Writing requirements only once', 'Compiling without running the program'], 0],
                [$intermediate, 'What does continuous integration commonly do?', ['Frequently integrates changes and runs automated checks', 'Deploys every change without tests or review', 'Prevents developers from using branches', 'Replaces source control with email'], 0],
                [$intermediate, 'What is a regression test intended to detect?', ['Previously working behavior that broke after a change', 'A user’s forgotten password', 'A compiler’s supported file format', 'A missing feature request'], 0],
                [$intermediate, 'What is a key idea in iterative development?', ['Delivering and refining software through repeated increments', 'Completing all testing before requirements exist', 'Avoiding user feedback until the end', 'Keeping every design decision secret'], 0],
                [$intermediate, 'What does separation of concerns aim to achieve?', ['Keeping distinct responsibilities from being unnecessarily entangled', 'Putting all logic in one large function', 'Removing module boundaries', 'Duplicating every business rule'], 0],
                [$intermediate, 'Why should a deployment plan include rollback steps?', ['To restore a known-good version if a release causes problems', 'To prevent users from reporting issues', 'To remove the need for backups', 'To make tests run more slowly'], 0],
            ],
        ],
        [
            'Computer Organization and Architecture', 'Computer Science Fundamentals',
            'Study how processors, memory, instructions, caches, and data representation work together.',
            25, $intermediate, [
                [$beginner, 'What is the ALU primarily responsible for?', ['Arithmetic and logical operations', 'Long-term file storage', 'Network routing', 'User authentication'], 0],
                [$beginner, 'What is a CPU register?', ['A small, fast storage location inside the processor', 'A type of hard disk partition', 'A database table', 'An operating-system user account'], 0],
                [$beginner, 'Which number system uses only 0 and 1?', ['Decimal', 'Hexadecimal', 'Binary', 'Octal'], 2],
                [$beginner, 'What does volatile main memory mean?', ['Its contents are generally lost when power is removed', 'It can only store program instructions', 'It is slower than all secondary storage', 'It cannot be written by a processor'], 0],
                [$beginner, 'What sequence describes a basic instruction cycle?', ['Fetch, decode, execute', 'Compile, link, print', 'Open, read, delete', 'Connect, route, disconnect'], 0],
                [$intermediate, 'What does a program counter typically hold?', ['The address of the next instruction to fetch', 'The number of active users', 'The CPU temperature', 'The contents of the entire cache'], 0],
                [$intermediate, 'Why can a processor cache improve performance?', ['It keeps frequently or recently used data closer to the CPU', 'It permanently replaces main memory', 'It increases program source-code size', 'It removes the need for registers'], 0],
                [$intermediate, 'What is instruction pipelining?', ['Overlapping stages of multiple instructions to improve throughput', 'Executing every instruction on a separate computer', 'Compressing the instruction set at runtime', 'Storing instructions only on disk'], 0],
                [$intermediate, 'What does endianness describe?', ['The byte order used to represent multi-byte values', 'The order in which programs are installed', 'The speed of a memory bus', 'The size of a processor register file'], 0],
                [$intermediate, 'What is a common design characteristic of RISC instruction sets?', ['A relatively small set of simple instructions', 'Every instruction requires a database lookup', 'Instructions cannot use registers', 'All operations must access disk'], 0],
            ],
        ],
        [
            'Theory of Computation', 'Computer Science Fundamentals',
            'Explore formal languages, finite automata, grammars, pushdown automata, and computability.',
            25, $intermediate, [
                [$beginner, 'What kind of language can a finite automaton recognize?', ['Regular languages', 'Only context-sensitive languages', 'Every undecidable language', 'Only natural-language prose'], 0],
                [$beginner, 'What is a state in a finite automaton?', ['A condition representing the machine’s current progress', 'A source-code variable declaration', 'A database transaction', 'A physical memory address'], 0],
                [$beginner, 'What is the input alphabet of an automaton?', ['The finite set of symbols it can read', 'The set of all possible machine states', 'The list of accepted programs', 'The order of grammar rules'], 0],
                [$beginner, 'What does a deterministic finite automaton have for each state and input symbol?', ['Exactly one defined next state', 'An unlimited stack', 'A separate operating system', 'A different input alphabet'], 0],
                [$beginner, 'What does a formal grammar specify?', ['Rules for generating strings in a language', 'Rules for scheduling CPU tasks', 'A database connection protocol', 'A method for compressing images'], 0],
                [$intermediate, 'What extra memory does a pushdown automaton use?', ['A stack', 'A second input tape only', 'A relational table', 'A CPU cache'], 0],
                [$intermediate, 'Which model is commonly used to define general computability?', ['Turing machine', 'Finite-state transducer only', 'Binary search tree', 'Relational algebra'], 0],
                [$intermediate, 'Which grammar class generates context-free languages?', ['Type-2 grammars in the Chomsky hierarchy', 'Type-0 grammars only', 'Regular expressions only', 'Boolean circuits only'], 0],
                [$intermediate, 'What does it mean for a decision problem to be decidable?', ['An algorithm halts with a correct yes/no answer for every input', 'It can be solved only by a human', 'It has at least one valid input', 'It always requires exponential time'], 0],
                [$intermediate, 'What does closure under union mean for a language class?', ['The union of two languages in the class is also in the class', 'Every language in the class is finite', 'All languages in the class are identical', 'The class contains no empty language'], 0],
            ],
        ],
        [
            'Compiler Design', 'Computer Science Fundamentals',
            'Follow source code through lexical analysis, parsing, semantic checks, optimization, and code generation.',
            25, $intermediate, [
                [$beginner, 'What is the primary output of lexical analysis?', ['A sequence of tokens', 'Machine code directly', 'A database schema', 'A network packet'], 0],
                [$beginner, 'What does a parser primarily check?', ['Whether tokens follow the language grammar', 'Whether a program has enough disk space', 'Whether every variable has a network address', 'Whether source files are encrypted'], 0],
                [$beginner, 'What is a token?', ['A categorized unit such as an identifier, keyword, or operator', 'A completed executable file', 'A CPU scheduling interval', 'A database password'], 0],
                [$beginner, 'What is an abstract syntax tree?', ['A tree representation of a program’s syntactic structure', 'A copy of the source file stored in RAM', 'A list of runtime network connections', 'A table of compiler errors only'], 0],
                [$beginner, 'What does semantic analysis commonly check?', ['Meaning-related rules such as type compatibility and declarations', 'Only whitespace and indentation', 'The speed of the target CPU', 'The source-control branch name'], 0],
                [$intermediate, 'What information is commonly stored in a compiler symbol table?', ['Names and attributes of declared program entities', 'Every runtime screen pixel', 'The operating system boot sequence', 'The full contents of the network stack'], 0],
                [$intermediate, 'What is an intermediate representation used for?', ['A machine-independent form for analysis and transformation', 'A user password database', 'A replacement for source code comments', 'A physical CPU instruction register'], 0],
                [$intermediate, 'What is a typical goal of compiler optimization?', ['Improve generated code while preserving program behavior', 'Change the program’s required output', 'Remove all error checking', 'Make source code longer'], 0],
                [$intermediate, 'What does code generation produce?', ['Target instructions or another executable representation', 'The original requirements document', 'A set of UI wireframes', 'A user account'], 0],
                [$intermediate, 'What is the linker responsible for in a typical build?', ['Combining object files and resolving external symbols', 'Tokenizing source code', 'Choosing variable names', 'Running database migrations'], 0],
            ],
        ],
        [
            'HTML & CSS', 'Web and Application Development',
            'Create accessible page structure with semantic HTML and style responsive layouts using CSS.',
            15, $beginner, [
                [$beginner, 'Which HTML element creates a hyperlink?', ['<a>', '<link-text>', '<href>', '<navlink>'], 0],
                [$beginner, 'Which attribute supplies alternative text for an informative image?', ['title', 'alt', 'srcset', 'role'], 1],
                [$beginner, 'Which HTML element is most appropriate for a page’s primary navigation links?', ['<nav>', '<span>', '<aside>', '<footer>'], 0],
                [$beginner, 'Which CSS property changes the text color?', ['font-style', 'text-color', 'color', 'foreground'], 2],
                [$beginner, 'Which selector targets an element with class="card"?', ['#card', '.card', 'card()', '@card'], 1],
                [$intermediate, 'In the CSS box model, what does padding describe?', ['Space between content and border', 'Space outside the border', 'The element’s font size', 'The position of a sibling element'], 0],
                [$intermediate, 'What does a CSS media query help create?', ['Styles that respond to viewport or device conditions', 'A database query', 'A video stream', 'An HTML form submission'], 0],
                [$intermediate, 'Which CSS layout system is designed for arranging items along rows or columns?', ['Flexbox', 'SQL', 'XML', 'Canvas-only positioning'], 0],
                [$intermediate, 'Why should an id value generally be unique within an HTML document?', ['It identifies one element for targeting and references', 'It automatically adds a heading', 'It makes every element responsive', 'It replaces all class selectors'], 0],
                [$intermediate, 'Which element is usually best for a self-contained, independently meaningful piece of content?', ['<article>', '<b>', '<br>', '<meta>'], 0],
            ],
        ],
        [
            'React.js Development', 'Web and Application Development',
            'Build component-based interfaces with React props, state, effects, and predictable rendering patterns.',
            25, $intermediate, [
                [$beginner, 'What is a React component?', ['A reusable unit that describes part of a user interface', 'A SQL database index', 'A browser extension only', 'A CSS color token'], 0],
                [$beginner, 'What does JSX provide?', ['A syntax for describing UI markup within JavaScript', 'A replacement for HTTP', 'A database migration format', 'A way to write CSS without selectors'], 0],
                [$beginner, 'What are props commonly used for?', ['Passing inputs from a parent component to a child', 'Changing the browser’s DNS settings', 'Storing a password in local storage', 'Creating a database table'], 0],
                [$beginner, 'Which React hook is commonly used for local component state?', ['useState', 'useRouteTable', 'useMarkup', 'useStyleSheet'], 0],
                [$beginner, 'Why should list items have stable keys?', ['To help React match items between renders', 'To encrypt each item', 'To create a CSS class automatically', 'To prevent component props'], 0],
                [$intermediate, 'When is useEffect commonly appropriate?', ['For synchronizing with external systems after rendering', 'For replacing every event handler', 'For creating HTML elements by hand only', 'For defining immutable props'], 0],
                [$intermediate, 'What is a controlled input in React?', ['An input whose value is driven by React state and updated by handlers', 'An input that cannot receive keyboard focus', 'An input styled only with CSS', 'An input stored only on the server'], 0],
                [$intermediate, 'What does lifting state up mean?', ['Moving shared state to a common ancestor of components that use it', 'Moving a component higher on the screen', 'Converting state to a CSS class', 'Copying state into every child'], 0],
                [$intermediate, 'How can a component render content conditionally?', ['Use JavaScript expressions or branches in its render logic', 'Use a database trigger', 'Use an HTML comment only', 'Rename the component file'], 0],
                [$intermediate, 'What is a reasonable use of useMemo?', ['Caching an expensive calculation when its dependencies have not changed', 'Making every value globally persistent', 'Replacing all state updates', 'Performing network requests during every render'], 0],
            ],
        ],
        [
            'PHP & MySQL Web Development', 'Web and Application Development',
            'Connect server-side PHP applications to MySQL with prepared statements, validation, sessions, and transactions.',
            25, $intermediate, [
                [$beginner, 'Which PHP extension is commonly used for a PDO database connection?', ['PDO', 'GD only', 'FTP', 'XMLReader'], 0],
                [$beginner, 'What is a prepared statement primarily useful for?', ['Separating SQL structure from parameter values', 'Making every query run without a database', 'Hiding all database errors permanently', 'Converting PHP into JavaScript'], 0],
                [$beginner, 'Which function safely hashes a password for later verification?', ['md5()', 'password_hash()', 'base64_encode()', 'str_shuffle()'], 1],
                [$beginner, 'What does htmlspecialchars help protect against when outputting untrusted text in HTML?', ['HTML/script injection through special characters', 'Slow database indexes', 'Expired sessions', 'Incorrect foreign keys'], 0],
                [$beginner, 'What is a PHP session commonly used for?', ['Keeping server-side state associated with a visitor across requests', 'Storing source code in the browser cache', 'Replacing the database server', 'Compiling CSS'], 0],
                [$intermediate, 'What does a database transaction provide?', ['A group of operations that can commit together or roll back', 'Automatic translation of SQL to HTML', 'A permanent lock on every table', 'A guarantee that a query contains no bugs'], 0],
                [$intermediate, 'What does an INNER JOIN return?', ['Rows with matching values in both joined relations', 'Every possible row from both tables', 'Only rows with NULL values', 'A sorted copy of one table'], 0],
                [$intermediate, 'What is the purpose of a foreign key?', ['Enforcing a relationship to a referenced key', 'Encrypting a column automatically', 'Replacing all database indexes', 'Storing PHP session data'], 0],
                [$intermediate, 'Why should a web application validate data on the server?', ['Clients can bypass or alter browser-side checks', 'JavaScript cannot display errors', 'HTML forms never submit strings', 'Validation makes passwords unnecessary'], 0],
                [$intermediate, 'What is a useful property of a database index?', ['It can speed up selected lookups at the cost of storage and write work', 'It guarantees every query is constant time', 'It stores a backup of the whole database', 'It prevents all duplicate values without a constraint'], 0],
            ],
        ],
        [
            'Full Stack Web Development', 'Web and Application Development',
            'Understand how browser interfaces, application APIs, databases, security, and deployment fit together.',
            25, $intermediate, [
                [$beginner, 'What is the browser primarily responsible for in a web application?', ['Rendering the interface and interacting with the user', 'Managing the production database backups', 'Issuing TLS certificates for the server', 'Scheduling all backend jobs'], 0],
                [$beginner, 'What does an HTTP server commonly do?', ['Receives requests and returns responses', 'Compiles every browser extension', 'Stores only CSS variables', 'Replaces the client network stack'], 0],
                [$beginner, 'Which format is commonly used to exchange structured data with a web API?', ['JSON', 'MP3', 'BMP', 'EXE'], 0],
                [$beginner, 'What is an API endpoint?', ['A network-accessible address for a particular operation or resource', 'A CSS breakpoint', 'A database backup file', 'A browser bookmark folder'], 0],
                [$beginner, 'Where should production secrets such as database passwords generally be kept?', ['In protected environment configuration, not committed source code', 'In a public JavaScript bundle', 'In a page title', 'In a public repository README'], 0],
                [$intermediate, 'What is the role of server-side authorization?', ['Checking whether an authenticated user may perform an action', 'Checking only whether a button is visible', 'Formatting a user’s screen', 'Encrypting image files'], 0],
                [$intermediate, 'What does CORS control in browsers?', ['Which cross-origin requests browser scripts may access under server policy', 'How database tables are indexed', 'The order of CSS declarations', 'How passwords are hashed'], 0],
                [$intermediate, 'Why should an API validate request data even when the frontend validates it?', ['Requests can be sent without using the trusted frontend', 'Frontend validation always changes request encoding', 'The server cannot read JSON otherwise', 'It automatically improves network speed'], 0],
                [$intermediate, 'What is a common responsibility of a database transaction in a web workflow?', ['Keeping related updates consistent as one unit', 'Rendering responsive layouts', 'Managing browser history', 'Compressing frontend images'], 0],
                [$intermediate, 'What does HTTPS provide when correctly configured?', ['Encrypted transport and server authentication using TLS', 'A guarantee that application logic is bug-free', 'Automatic database authorization', 'A replacement for user authentication'], 0],
            ],
        ],
        [
            'Artificial Intelligence', 'Artificial Intelligence and Data Science',
            'Survey intelligent systems, search, knowledge representation, learning paradigms, and evaluation basics.',
            20, $beginner, [
                [$beginner, 'Which statement best describes artificial intelligence?', ['Methods for building systems that perform tasks associated with intelligent behavior', 'A specific database engine', 'A markup language for websites', 'A single type of computer hardware'], 0],
                [$beginner, 'What is a common goal of a search algorithm in AI?', ['Finding a sequence of actions from an initial state to a goal', 'Changing a computer’s physical memory', 'Encrypting every input value', 'Formatting a report'], 0],
                [$beginner, 'What does a knowledge representation aim to encode?', ['Facts and relationships a system can reason with', 'Only the color of a user interface', 'The CPU clock frequency', 'A list of installed printers'], 0],
                [$beginner, 'What is an expert system designed to do?', ['Apply encoded domain knowledge and rules to a problem', 'Replace every operating system', 'Render videos faster', 'Store files without a file system'], 0],
                [$beginner, 'Which learning paradigm uses examples paired with known target labels?', ['Supervised learning', 'Unsupervised learning', 'Reinforcement learning only', 'Manual sorting'], 0],
                [$intermediate, 'What is overfitting?', ['A model learns training-specific patterns that generalize poorly', 'A model has no parameters', 'A dataset contains no features', 'A program runs faster than expected'], 0],
                [$intermediate, 'What does reinforcement learning use to guide an agent’s behavior?', ['Rewards or penalties from interaction with an environment', 'Only a fixed list of labeled outputs', 'CSS selectors', 'Database primary keys'], 0],
                [$intermediate, 'What is the purpose of a held-out test set?', ['Estimating performance on data not used to fit the model', 'Choosing the programming language', 'Encoding all training labels twice', 'Removing the need for evaluation'], 0],
                [$intermediate, 'What does precision measure in binary classification?', ['The fraction of predicted positives that are truly positive', 'The fraction of all negatives predicted correctly', 'The number of input features', 'The average training time'], 0],
                [$intermediate, 'What is a heuristic in a search problem?', ['A rule or estimate that helps guide search toward promising states', 'A guarantee of the globally optimal answer in every problem', 'A type of labeled database row', 'A method for hashing passwords'], 0],
            ],
        ],
        [
            'Machine Learning', 'Artificial Intelligence and Data Science',
            'Practice core machine-learning concepts including features, labels, model fitting, evaluation, and data leakage.',
            25, $intermediate, [
                [$beginner, 'What is a feature in a machine-learning dataset?', ['An input variable used by a model', 'The final deployed application only', 'A model’s error message', 'A database backup schedule'], 0],
                [$beginner, 'What is a label in supervised learning?', ['The target value a model is trained to predict', 'A random identifier for a computer', 'A feature normalization method', 'A database table name'], 0],
                [$beginner, 'What does a classification model usually predict?', ['A discrete category or class', 'Only a continuous measurement', 'A file-system path', 'A network protocol'], 0],
                [$beginner, 'What does a regression model usually predict?', ['A continuous numeric value', 'A CSS class name only', 'A database schema', 'A sequence of source files'], 0],
                [$beginner, 'Why is data split into training and test sets?', ['To evaluate generalization on examples not used for training', 'To guarantee perfect accuracy', 'To remove all outliers automatically', 'To increase the number of labels'], 0],
                [$intermediate, 'What is cross-validation commonly used for?', ['Estimating model performance across multiple data splits', 'Encrypting model parameters', 'Converting classification to regression', 'Selecting a database password'], 0],
                [$intermediate, 'What does gradient descent do during many model-training procedures?', ['Updates parameters using the direction of a loss gradient', 'Randomly deletes all model parameters', 'Sorts test labels alphabetically', 'Generates new target classes from CSS'], 0],
                [$intermediate, 'Why can feature scaling help some machine-learning algorithms?', ['It puts numeric features on comparable scales', 'It guarantees that no data is missing', 'It increases the number of observations', 'It replaces the need for a test set'], 0],
                [$intermediate, 'What is data leakage?', ['Information unavailable at prediction time improperly influences training or evaluation', 'A secure backup sent to another region', 'A model with too few parameters', 'A missing database index'], 0],
                [$intermediate, 'What does a confusion matrix summarize?', ['Counts of actual versus predicted classes', 'The order of model layers', 'The number of database tables', 'The average size of training files'], 0],
            ],
        ],
        [
            'Data Science Fundamentals', 'Artificial Intelligence and Data Science',
            'Build data literacy with exploration, summary statistics, cleaning, visualization, and sound interpretation.',
            20, $beginner, [
                [$beginner, 'What is exploratory data analysis used for?', ['Understanding patterns, distributions, and possible data issues', 'Deploying a web server', 'Encrypting every dataset column', 'Replacing all statistical tests'], 0],
                [$beginner, 'Which measure is the middle value after sorting observations?', ['Mean', 'Median', 'Range', 'Variance'], 1],
                [$beginner, 'What is a useful first step when a dataset contains missing values?', ['Inspect their extent and pattern before choosing a treatment', 'Replace every missing value with zero automatically', 'Delete the entire dataset', 'Assume they are all correct labels'], 0],
                [$beginner, 'What does a bar chart commonly compare?', ['Values across discrete categories', 'Only continuous time signals', 'Source-code dependencies', 'Database transaction locks'], 0],
                [$beginner, 'What does a spreadsheet or data frame organize?', ['Tabular observations and variables', 'Only image pixels', 'Network routing rules', 'Audio waveforms only'], 0],
                [$intermediate, 'Why does correlation alone not establish causation?', ['Association can arise without one variable causing the other', 'Correlation is always negative', 'Causation cannot be studied scientifically', 'A correlation coefficient is a category label'], 0],
                [$intermediate, 'What is sample bias?', ['A sampling process that systematically misrepresents the target population', 'A random error in a chart legend', 'A database foreign-key mismatch', 'A standard way to normalize all features'], 0],
                [$intermediate, 'What does standard deviation describe?', ['Typical spread of values around the mean', 'The most frequent category only', 'The number of columns in a table', 'The slope of every regression line'], 0],
                [$intermediate, 'What is a hypothesis test designed to assess?', ['Whether observed data provide evidence against a specified null hypothesis', 'Whether a website supports mobile screens', 'Whether every sample is unbiased', 'Whether a database has a primary key'], 0],
                [$intermediate, 'When can the median be preferable to the mean?', ['When a distribution has extreme outliers that distort the mean', 'When all observations are text labels', 'When every value must be unique', 'When there are no observations'], 0],
            ],
        ],
        [
            'Deep Learning', 'Artificial Intelligence and Data Science',
            'Learn neural-network foundations, training, convolution, sequence models, attention, and regularization.',
            25, $intermediate, [
                [$beginner, 'What is an artificial neuron in a neural network?', ['A computation that combines inputs, weights, a bias, and an activation', 'A physical CPU core', 'A database relation', 'A web browser extension'], 0],
                [$beginner, 'What does an activation function add to a neural network?', ['Nonlinear behavior that enables richer function modeling', 'A database connection', 'An automatic test set', 'A guarantee of zero training error'], 0],
                [$beginner, 'What is an epoch in model training?', ['One pass through the training dataset', 'A single model parameter', 'A type of activation function', 'The time needed to compile source code'], 0],
                [$beginner, 'What is a batch during training?', ['A subset of training examples processed together for an update', 'A set of GPU drivers', 'A group of output classes only', 'A list of model names'], 0],
                [$beginner, 'What does a loss function measure?', ['A numerical measure of prediction error for optimization', 'The amount of disk space in a dataset', 'The model’s source-code length', 'The number of hidden layers only'], 0],
                [$intermediate, 'What is backpropagation used for?', ['Computing gradients of a loss with respect to network parameters', 'Creating labels without data', 'Compressing every model layer', 'Selecting a cloud region'], 0],
                [$intermediate, 'Why are convolutional neural networks often effective for images?', ['They use local receptive fields and shared filters to detect spatial patterns', 'They require no training data', 'They represent every pixel as a database table', 'They cannot use nonlinear activations'], 0],
                [$intermediate, 'What does attention allow a model to do?', ['Weight relationships between elements in a sequence or input', 'Automatically remove all noisy labels', 'Guarantee causal conclusions', 'Replace all model parameters with rules'], 0],
                [$intermediate, 'What is dropout commonly used for during training?', ['Randomly disabling selected activations as a regularization technique', 'Increasing every model weight', 'Replacing the validation set', 'Converting images into SQL queries'], 0],
                [$intermediate, 'What is a sequence model designed to process?', ['Ordered data such as text or time series', 'Only unordered database schemas', 'Only single scalar constants', 'Compiler error messages exclusively'], 0],
            ],
        ],
        [
            'Cloud Computing and AWS', 'Advanced Technologies',
            'Learn cloud fundamentals and common AWS services for compute, storage, identity, monitoring, and networking.',
            25, $intermediate, [
                [$beginner, 'Which AWS service provides resizable virtual server capacity?', ['Amazon EC2', 'Amazon S3', 'Amazon Route 53 only', 'AWS Artifact'], 0],
                [$beginner, 'Which AWS service is commonly used for object storage?', ['Amazon S3', 'Amazon EC2', 'Amazon VPC', 'AWS IAM'], 0],
                [$beginner, 'What is an AWS Region?', ['A separate geographic area containing AWS infrastructure', 'A single user account', 'A type of database query', 'A local browser cache'], 0],
                [$beginner, 'What is AWS Identity and Access Management (IAM) used for?', ['Managing identities and permissions', 'Rendering HTML pages', 'Compressing virtual machines', 'Replacing network firewalls'], 0],
                [$beginner, 'What is a key cloud benefit of on-demand resources?', ['Capacity can be provisioned when needed without buying all hardware upfront', 'Every workload becomes free', 'Applications no longer need security', 'Network latency is always zero'], 0],
                [$intermediate, 'What does the principle of least privilege recommend?', ['Grant only the permissions needed for a task', 'Give every user administrator access', 'Share one root credential across teams', 'Disable audit logs'], 0],
                [$intermediate, 'What is Amazon RDS designed to provide?', ['Managed relational database services', 'Object storage for arbitrary files', 'A global DNS registrar only', 'A source-code editor'], 0],
                [$intermediate, 'What is AWS Lambda commonly used for?', ['Running event-driven code without managing the underlying server fleet', 'Hosting static IP routing tables only', 'Storing container images only', 'Creating IAM users automatically'], 0],
                [$intermediate, 'What is Amazon CloudWatch commonly used for?', ['Monitoring metrics, logs, and alarms', 'Managing source-code branches', 'Replacing IAM policies', 'Authoring SQL schemas'], 0],
                [$intermediate, 'What is a VPC in AWS?', ['A logically isolated virtual network for AWS resources', 'A serverless function runtime', 'A managed relational database', 'A cloud billing invoice'], 0],
            ],
        ],
        [
            'Cyber Security Fundamentals', 'Advanced Technologies',
            'Practice security fundamentals including identity, secure coding, encryption, risk reduction, and incident response.',
            20, $beginner, [
                [$beginner, 'What does the confidentiality part of the CIA triad mean?', ['Information is accessible only to authorized parties', 'Systems always respond within one second', 'Every file has a backup', 'Users can edit every record'], 0],
                [$beginner, 'What does multi-factor authentication require?', ['Evidence from more than one authentication factor', 'Two passwords stored in the same field', 'A username without a password', 'A public link to the account'], 0],
                [$beginner, 'What is phishing?', ['A deceptive attempt to trick people into revealing information or taking unsafe actions', 'A method for encrypting database backups', 'A safe software update process', 'A type of network cable'], 0],
                [$beginner, 'Why are software security updates important?', ['They can fix known vulnerabilities and defects', 'They guarantee an application has no vulnerabilities', 'They replace access controls', 'They make backups unnecessary'], 0],
                [$beginner, 'What is the principle of least privilege?', ['Give each user or process only the access required for its task', 'Give all accounts identical permissions', 'Avoid reviewing permissions', 'Use one shared administrator login'], 0],
                [$intermediate, 'What is the primary difference between hashing and encryption?', ['Hashing is generally one-way; encryption is designed to be reversible with a key', 'Hashing always uses a password; encryption never does', 'Encryption cannot protect data', 'They are identical operations'], 0],
                [$intermediate, 'How do parameterized SQL statements help reduce SQL injection risk?', ['They keep supplied values separate from SQL syntax', 'They remove the need for database permissions', 'They encrypt the entire database automatically', 'They block every type of web attack'], 0],
                [$intermediate, 'What does TLS help provide for a network connection?', ['Encryption and integrity, with authentication when certificates are validated', 'A guarantee that a website’s business logic is correct', 'Automatic protection from all malware', 'Password recovery without identity checks'], 0],
                [$intermediate, 'What is a useful first objective during incident response containment?', ['Limit the incident’s spread while preserving evidence where practical', 'Delete every log immediately', 'Disable all backups', 'Publish affected credentials'], 0],
                [$intermediate, 'Why should backups be tested by restoring them?', ['To verify that backup data is usable and recovery procedures work', 'To increase the number of vulnerabilities', 'To avoid controlling backup access', 'To make incident response unnecessary'], 0],
            ],
        ],
    ];

    return $courses;
}

function expand_course_catalog(PDO $pdo): array
{
    $seedQuestionTexts = [];
    foreach (course_catalog_seed_data() as $course) {
        foreach ($course[5] as $question) {
            $questionKey = mb_strtolower(trim($question[1]));
            if (isset($seedQuestionTexts[$questionKey])) {
                throw new RuntimeException("Duplicate seeded question: {$question[1]}");
            }
            $seedQuestionTexts[$questionKey] = true;
            if (count($question[2]) !== 4 || count(array_unique($question[2], SORT_STRING)) !== 4 || $question[3] < 0 || $question[3] > 3) {
                throw new RuntimeException("Invalid seed options for: {$question[1]}");
            }
        }
    }

    $columnExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    foreach ([
        ['quizzes', 'difficulty', "ENUM('Beginner','Intermediate') NOT NULL DEFAULT 'Beginner' AFTER `passing_percent`"],
        ['questions', 'difficulty', "ENUM('Beginner','Intermediate') NOT NULL DEFAULT 'Beginner' AFTER `marks`"],
    ] as [$table, $column, $definition]) {
        $columnExists->execute([$table, $column]);
        if ((int) $columnExists->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    $coursesInserted = 0;
    $questionsInserted = 0;
    $pdo->beginTransaction();
    try {
        $findCourse = $pdo->prepare('SELECT id FROM quizzes WHERE title = ? ORDER BY id LIMIT 1');
        $insertCourse = $pdo->prepare(
            'INSERT INTO quizzes (title, tag, intro, duration_minutes, passing_percent, difficulty, is_published) VALUES (?, ?, ?, ?, 50, ?, 1)'
        );
        $findQuestion = $pdo->prepare('SELECT id FROM questions WHERE quiz_id = ? AND text = ? ORDER BY id LIMIT 1');
        $insertQuestion = $pdo->prepare(
            'INSERT INTO questions (quiz_id, text, marks, difficulty, position) VALUES (?, ?, 1, ?, ?)'
        );
        $findOptions = $pdo->prepare(
            'SELECT text, is_correct FROM options WHERE question_id = ? ORDER BY position, id'
        );
        $insertOption = $pdo->prepare(
            'INSERT INTO options (question_id, text, is_correct, position) VALUES (?, ?, ?, ?)'
        );
        $nextPosition = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM questions WHERE quiz_id = ?');

        foreach (course_catalog_seed_data() as [$title, $category, $description, $duration, $courseDifficulty, $questions]) {
            $findCourse->execute([$title]);
            $quizId = $findCourse->fetchColumn();
            if ($quizId === false) {
                $insertCourse->execute([$title, $category, $description, $duration, $courseDifficulty]);
                $quizId = (int) $pdo->lastInsertId();
                $coursesInserted++;
            } else {
                $quizId = (int) $quizId;
            }

            $nextPosition->execute([$quizId]);
            $position = (int) $nextPosition->fetchColumn();
            foreach ($questions as [$questionDifficulty, $questionText, $options, $correctIndex]) {
                if (count($options) !== 4 || count(array_unique($options, SORT_STRING)) !== 4 || $correctIndex < 0 || $correctIndex > 3) {
                    throw new RuntimeException("Invalid seed options for: $questionText");
                }

                $findQuestion->execute([$quizId, $questionText]);
                $questionId = $findQuestion->fetchColumn();
                if ($questionId === false) {
                    $insertQuestion->execute([$quizId, $questionText, $questionDifficulty, $position]);
                    $questionId = (int) $pdo->lastInsertId();
                    $position++;
                    $questionsInserted++;
                    foreach ($options as $optionIndex => $optionText) {
                        $insertOption->execute([
                            $questionId,
                            $optionText,
                            $optionIndex === $correctIndex ? 1 : 0,
                            $optionIndex + 1,
                        ]);
                    }
                    continue;
                }

                $findOptions->execute([(int) $questionId]);
                $existingOptions = $findOptions->fetchAll();
                if (count($existingOptions) !== 4) {
                    throw new RuntimeException(
                        "Existing seeded question '$questionText' does not have exactly four options; no seed changes were committed."
                    );
                }
                foreach ($options as $optionIndex => $optionText) {
                    if (
                        $existingOptions[$optionIndex]['text'] !== $optionText
                        || (int) $existingOptions[$optionIndex]['is_correct'] !== ($optionIndex === $correctIndex ? 1 : 0)
                    ) {
                        throw new RuntimeException(
                            "Existing seeded options differ for '$questionText'; no seed changes were committed."
                        );
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return ['courses_inserted' => $coursesInserted, 'questions_inserted' => $questionsInserted];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/../../includes/db.php';
    $result = expand_course_catalog(db());
    printf(
        "Course catalog updated: %d courses inserted, %d questions inserted. Existing records were retained.\n",
        $result['courses_inserted'],
        $result['questions_inserted']
    );
} elseif (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
