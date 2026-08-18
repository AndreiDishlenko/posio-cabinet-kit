import { AxiosApiClientClass }          from "@/js/posio";
import { CustomError }                  from "@/js/posio/system/CustomErrorClass.js";

function init(settings) {
    
    let api_settings = {
            baseURL: window.location.origin + '/api/v1/',
            timeout: 10000,
            withCredentials: true,
            tokenAuthorization: false,
            signin_payload: {},
        }

    const FrontApiClient = new AxiosApiClientClass(api_settings)
    
    FrontApiClient.checkResponse = function(response) {
    }

    console.msg('[+] Api Client initialization finished');

    return FrontApiClient
}

export default init;