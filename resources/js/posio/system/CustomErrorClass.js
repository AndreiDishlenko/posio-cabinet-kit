class CustomErrorClass extends Error {
    constructor(message, parameters={}) {
        super(message);                     // Вызываем конструктор родительского класса Error
        this.error  = message;        
        this.params = parameters;
        Object.keys(parameters).forEach((key) => {
            this[key] = parameters[key];
        })
        // this.name   = this.constructor.name;  // Устанавливаем имя ошибки на имя класса
        // this.code = code; // Дополнительное свойство ошибки
    }
}

export { CustomErrorClass as CustomError }