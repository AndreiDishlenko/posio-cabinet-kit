export const Vue = {
    findId: (vue_datasource, id) => {
        // console.log('this', vue_datasource);
    
        let result;
        vue_datasource.forEach(item => {
            if (item.id == id)
                result = item;
        })
        return result;
    }
}